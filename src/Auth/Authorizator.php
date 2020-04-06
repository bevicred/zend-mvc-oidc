<?php

namespace Zend\Mvc\OIDC\Auth;

use Exception;
use Zend\Http\Request;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Enum\ValidationTokenResultEnum;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\CertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Model\Token;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;
use Zend\Mvc\OIDC\OpenIDConnect\CertKeyService;
use Zend\Mvc\OIDC\OpenIDConnect\ConfigurationDiscoveryService;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * Class Authorizator
 *
 * @package Zend\Mvc\OIDC\Auth
 */
class Authorizator
{

    /**
     * @var Token
     */
    private $token;

    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var ConfigurationParser
     */
    private $configurationParser;

    /**
     * @var array
     */
    private $routesConfig;

    /**
     * @var ConfigurationDiscoveryService
     */
    private $configurationDiscoveryService;

    /**
     * @var CertKeyService
     */
    private $certKeyService;

    /**
     * Authorizator constructor.
     *
     * @param array $moduleConfig
     * @param ServiceLocatorInterface $serviceManager
     *
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     * @throws AudienceConfigurationException
     */
    public function __construct(array $moduleConfig, ServiceLocatorInterface $serviceManager)
    {
        if (isset($moduleConfig['router']) && isset($moduleConfig['router']['routes'])) {
            $this->routesConfig = $moduleConfig['router']['routes'];
        }

        $this->serviceManager = $serviceManager;

        $this->configurationDiscoveryService = new ConfigurationDiscoveryService();

        $this->certKeyService = new CertKeyService();

        $this->configurationParser = new ConfigurationParser();
        $this->configuration = $this->configurationParser->parse($moduleConfig);
    }

    /**
     * @param Request $request
     *
     * @return bool
     * @throws BasicAuthorizationException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     * @throws Exception
     */
    public function authorize(Request $request): bool
    {
        $this->token = new Token($this->getAuthorizationToken($request));

        $authorizeConfig = $this->getAuthorizeConfiguration($request);

        $certKey = $this->certKeyService->resolveCertificate($this->configuration, $this->serviceManager);

        if (!is_null($certKey)) {
            $this->configuration->setPublicKey($certKey);
            $result = $this->token->validate($this->configuration);

            if ($result == ValidationTokenResultEnum::INVALID) {
                throw new InvalidAuthorizationTokenException('Invalid authorization token.');
            } else if ($result == ValidationTokenResultEnum::EXPIRED) {
                throw new InvalidAuthorizationTokenException('Expired authorization token.');
            }
        } else {
            throw new CertificateKeyException('Failed to retrieve the token certificate key.');
        }

        return $this->isAuthorized($authorizeConfig);
    }

    /**
     * @return array
     */
    public function getTokenClaims(): array
    {
        return $this->token->getClaims();
    }

    /**
     * @param array $authorizeConfig
     *
     * @return bool
     */
    private function isAuthorized(array $authorizeConfig): bool
    {
        $result = false;
        $claimName = $authorizeConfig['requireClaim'];

        foreach ($authorizeConfig['values'] as $claimValue) {
            if ($this->token->hasClaim($claimName, $claimValue)) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private function getAuthorizeConfiguration(Request $request): array
    {
        $url = $request->getUriString();

        if (isset($this->routesConfig[$url]) &&
            isset($this->routesConfig[$url]['options']['defaults']['authorize'])) {
            return $this->routesConfig[$url]['options']['defaults']['authorize'];
        }

        return [];
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
        $token = '';

        if (!is_null($headers)) {
            $token = $headers->toString();
            $token = str_replace('Authorization: Bearer', null, $token);

            return $token;
        }

        throw new BasicAuthorizationException('Authorization exception.');
    }
}