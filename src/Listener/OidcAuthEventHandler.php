<?php

namespace Zend\Mvc\OIDC\Listener;

use Exception;
use Zend\Http\Request;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ConfigurationEnum;
use Zend\Mvc\OIDC\Common\Enum\ServiceEnum;
use Zend\Mvc\OIDC\Common\Enum\ValidationTokenResultEnum;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\AuthorizeException;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidExceptionMappingConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Model\Token;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;
use Zend\Mvc\OIDC\Custom\AuthInformationProvider;
use Zend\Mvc\OIDC\Custom\Interfaces\AuthResultHandlerInterface;
use Zend\Mvc\OIDC\OpenIDConnect\CertKeyService;
use Zend\ServiceManager\ServiceManager;

/**
 * Class OidcAuthEventHandler
 *
 * @package Zend\Mvc\OIDC\Listener
 */
class OidcAuthEventHandler
{
    /**
     * @var array
     */
    private $routesConfig;

    /**
     * @var ConfigurationParser
     */
    private $configurationParser;
    /**
     * @var CertKeyService
     */
    private $certKeyService;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var Token
     */
    private $token;

    /**
     * OidcAuthEventHandler constructor.
     *
     * @param array $applicationConfig
     * @param array $moduleConfig
     * @param ConfigurationParser $configurationParser
     * @param CertKeyService $certKeyService
     *
     * @throws AudienceConfigurationException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     * @throws InvalidExceptionMappingConfigurationException
     */
    public function __construct(
        array $applicationConfig,
        array $moduleConfig,
        ConfigurationParser $configurationParser,
        CertKeyService $certKeyService
    ) {
        if (isset($moduleConfig['router']) && isset($moduleConfig['router']['routes'])) {
            $this->routesConfig = $moduleConfig['router']['routes'];
        }

        $this->configurationParser = $configurationParser;
        $this->certKeyService = $certKeyService;

        $this->configuration = $this->configurationParser->parse($applicationConfig);
    }

    /**
     * @param MvcEvent $mvcEvent
     *
     * @return MvcEvent
     */
    public function handle(MvcEvent $mvcEvent): MvcEvent
    {
        try {
            /** @var Request $request */
            $request = $mvcEvent->getRequest();

            $authorizeConfig = $this->getAuthorizeConfiguration($request);

            if (!$this->allowAnonymous($authorizeConfig)) {
                $this->token = new Token($this->getAuthorizationToken($request));

                /** @var ServiceManager $serviceManager */
                $serviceManager = $mvcEvent->getApplication()->getServiceManager();

                $certKey = $this->certKeyService->resolveCertificate(
                    $this->configuration,
                    $this->token->getHeaders(),
                    $serviceManager
                );

                $this->configuration->setPublicKey($certKey);
                $tokenValidationResult = $this->token->validate($this->configuration);

                $this->deliverAuthInformationProvider($serviceManager);

                $hasCustomResultRules = $serviceManager->has(ServiceEnum::AUTH_RESULT_HANDLER);

                if (!$hasCustomResultRules) {
                    $this->resolveTokenResultWithStockRule($tokenValidationResult);
                }

                $authorizationResult = $this->isAuthorized($authorizeConfig);

                if ($hasCustomResultRules) {
                    $this->resolveDelegateResult($tokenValidationResult, $authorizationResult, $serviceManager);
                } else if (!$authorizationResult) {
                    $this->resolveAuthorizationException();
                }
            }
        } catch (\Throwable $ex) {
            $mvcEvent->setError('AuthError');
            $mvcEvent->setParam('exception', $ex);

            $mvcEvent->stopPropagation(true);
            $mvcEvent->setName(MvcEvent::EVENT_DISPATCH_ERROR);

            $target = $mvcEvent->getTarget();
            $target->getEventManager()->triggerEvent($mvcEvent);
        }

        return $mvcEvent;
    }

    /**
     * @param int $tokenValidationResult
     * @param bool $authorizationResult
     * @param ServiceManager $serviceManager
     *
     */
    private function resolveDelegateResult(
        int $tokenValidationResult,
        bool $authorizationResult,
        ServiceManager $serviceManager
    ): void {
        /** @var AuthResultHandlerInterface $authResultHandler */
        $authResultHandler = $serviceManager->get(ServiceEnum::AUTH_RESULT_HANDLER);
        $authResultHandler->handle($tokenValidationResult, $authorizationResult);
    }

    /**
     * @param int $result
     *
     * @throws InvalidAuthorizationTokenException
     */
    private function resolveTokenResultWithStockRule(int $result): void
    {
        if ($result == ValidationTokenResultEnum::INVALID) {
            $this->resolveInvalidTokenException();
        } else if ($result == ValidationTokenResultEnum::EXPIRED) {
            $this->resolveExpiredTokenException();
        }
    }

    /**
     * @param ServiceManager $serviceManager
     *
     * @throws \ReflectionException
     */
    private function deliverAuthInformationProvider(
        ServiceManager $serviceManager
    ): void {
        $authInformationProvider = $this->createAuthInformationProvider($this->token->getClaims());

        $serviceManager->setService(AuthInformationProvider::class, $authInformationProvider);
    }

    /**
     * @throws InvalidAuthorizationTokenException
     */
    private function resolveInvalidTokenException(): void
    {
        $exceptionClass = $this->configuration->getInvalidTokenExceptionMapping();

        if (is_null($exceptionClass)) {
            throw new InvalidAuthorizationTokenException('Invalid authorization token.');
        } else {
            throw new $exceptionClass('Invalid authorization token.');
        }
    }

    /**
     * @throws InvalidAuthorizationTokenException
     */
    private function resolveExpiredTokenException(): void
    {
        $exceptionClass = $this->configuration->getExpiredTokenExceptionMapping();

        if (is_null($exceptionClass)) {
            throw new InvalidAuthorizationTokenException('Expired authorization token.');
        } else {
            throw new $exceptionClass('Expired authorization token.');
        }
    }

    /**
     * @throws AuthorizeException
     */
    private function resolveAuthorizationException(): void
    {
        $exceptionClass = $this->configuration->getForbiddenTokenExceptionMapping();

        if (is_null($exceptionClass)) {
            throw new AuthorizeException('Authorization failed.');
        } else {
            throw new $exceptionClass('Authorization failed.');
        }
    }

    /**
     * @param array $authorizeConfig
     *
     * @return bool
     */
    private function allowAnonymous(array $authorizeConfig): bool
    {
        return (count(
                $authorizeConfig
            ) > 0 && isset($authorizeConfig[0]) && $authorizeConfig[0] == ConfigurationEnum::ALLOW_ANONYMOUS);
    }

    /**
     * @param array $authorizeConfig
     *
     * @return bool
     */
    private function isAuthorized(array $authorizeConfig): bool
    {
        $result = false;
        $claimName = $authorizeConfig[ConfigurationEnum::REQUIRE_CLAIM];

        foreach ($authorizeConfig['values'] as $claimValue) {
            if ($this->token->hasClaim($claimName, $claimValue)) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * @param array $claimsFromToken
     *
     * @return AuthInformationProvider
     * @throws \ReflectionException
     */
    private function createAuthInformationProvider(array $claimsFromToken): AuthInformationProvider
    {
        $authInformationProvider = new AuthInformationProvider();

        $outClaims = [];
        foreach ($claimsFromToken as $key => $value) {
            $outClaims[$key] = $value;
        }

        $reflection = new \ReflectionObject($authInformationProvider);
        $property = $reflection->getProperty('claims');
        $property->setAccessible(true);
        $property->setValue($authInformationProvider, $outClaims);

        return $authInformationProvider;
    }

    /**
     * @param Request $request
     *
     * @return string
     * @throws InvalidAuthorizationTokenException
     */
    private function getAuthorizationToken(Request $request): string
    {
        $headers = $request->getHeaders('Authorization', null);

        if (!is_null($headers)) {
            $tokenFromHeader = $headers->toString();
            return str_replace('Authorization: Bearer ', null, $tokenFromHeader);
        }

        $this->resolveInvalidTokenException();
    }

    /**
     * @param Request $request
     *
     * @return array
     * @throws InvalidAuthorizationTokenException
     */
    private function getAuthorizeConfiguration(Request $request): array
    {
        $url = $request->getUri()->getPath();

        if (isset($this->routesConfig[$url]) &&
            isset($this->routesConfig[$url]['options']['defaults'][ConfigurationEnum::AUTHORIZE_CONFIG])) {
            return $this->routesConfig[$url]['options']['defaults'][ConfigurationEnum::AUTHORIZE_CONFIG];
        }

        $this->resolveInvalidTokenException();
    }
}