<?php

namespace Zend\Mvc\OIDC\Listener;

use Exception;
use Zend\Http\Request;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ConfigurationEnum;
use Zend\Mvc\OIDC\Common\Enum\ValidationTokenResultEnum;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\AuthorizeException;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Model\Token;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;
use Zend\Mvc\OIDC\OpenIDConnect\CertKeyService;

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
     * @throws AuthorizeException
     * @throws BasicAuthorizationException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     * @throws Exception
     */
    public function handle(MvcEvent $mvcEvent): MvcEvent
    {
        /** @var Request $request */
        $request = $mvcEvent->getRequest();
        $this->token = new Token($this->getAuthorizationToken($request));

        $authorizeConfig = $this->getAuthorizeConfiguration($request);

        if (!$this->allowAnonymous($authorizeConfig)) {
            $certKey = $this->certKeyService->resolveCertificate(
                $this->configuration,
                $this->token->getHeaders(),
                $mvcEvent->getApplication()->getServiceManager()
            );

            $this->configuration->setPublicKey($certKey);
            $result = $this->token->validate($this->configuration);

            if ($result == ValidationTokenResultEnum::INVALID) {
                throw new InvalidAuthorizationTokenException('Invalid authorization token.');
            } else if ($result == ValidationTokenResultEnum::EXPIRED) {
                throw new InvalidAuthorizationTokenException('Expired authorization token.');
            }

            $this->isAuthorized($authorizeConfig);
        }

        return $mvcEvent;
    }

    private function allowAnonymous(array $authorizeConfig): bool
    {
        return (count($authorizeConfig) > 0 && isset($authorizeConfig[0]) && $authorizeConfig[0] == ConfigurationEnum::ALLOW_ANONYMOUS);
    }

    /**
     * @param array $authorizeConfig
     *
     * @throws AuthorizeException
     */
    private function isAuthorized(array $authorizeConfig): void
    {
        $result = false;
        $claimName = $authorizeConfig[ConfigurationEnum::REQUIRE_CLAIM];

        foreach ($authorizeConfig['values'] as $claimValue) {
            if ($this->token->hasClaim($claimName, $claimValue)) {
                $result = true;
                break;
            }
        }

        if (!$result) {
            throw new AuthorizeException('Authorization failed.');
        }
    }

    /**
     * @param Request $request
     *
     * @return string
     * @throws BasicAuthorizationException
     */
    private function getAuthorizationToken(Request $request): string
    {
        $headers = $request->getHeaders('Authorization', null);

        if (!is_null($headers)) {
            $tokenFromHeader = $headers->toString();
            return str_replace('Authorization: Bearer ', null, $tokenFromHeader);
        }

        throw new BasicAuthorizationException('Authorization exception.');
    }

    /**
     * @param Request $request
     *
     * @return array
     * @throws AuthorizeException
     */
    private function getAuthorizeConfiguration(Request $request): array
    {
        $url = $request->getUriString();

        if (isset($this->routesConfig[$url]) &&
            isset($this->routesConfig[$url]['options']['defaults'][ConfigurationEnum::AUTHORIZE_CONFIG])) {
            return $this->routesConfig[$url]['options']['defaults'][ConfigurationEnum::AUTHORIZE_CONFIG];
        }

        throw new AuthorizeException('Authorization failed.');
    }
}