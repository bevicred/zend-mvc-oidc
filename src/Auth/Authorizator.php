<?php

namespace Zend\Mvc\OIDC\Auth;

use Zend\Http\Request;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Model\Token;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\RequestInterface;

/**
 * Class Authorizator
 *
 * @package Zend\Mvc\OIDC\Auth
 */
class Authorizator
{

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
     * Authorizator constructor.
     *
     * @param array $moduleConfig
     * @param ServiceLocatorInterface $serviceManager
     *
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    public function __construct(array $moduleConfig, ServiceLocatorInterface $serviceManager)
    {
        if (isset($moduleConfig['router']) && isset($moduleConfig['router']['routes'])) {
            $this->routesConfig = $moduleConfig['router']['routes'];
        }

        $this->serviceManager = $serviceManager;

        $this->configurationParser = new ConfigurationParser();
        $this->configuration = $this->configurationParser->parse($moduleConfig);
    }

    /**
     * @param Request $request
     *
     * @return bool
     * @throws BasicAuthorizationException
     */
    public function authorize(Request $request): bool
    {
        $headerToken = new Token($this->getAuthorizationToken($request));

        $authorizeConfig = $this->getAuthorizeConfiguration($request);

        return $this->isAuthorized($headerToken, $authorizeConfig);
    }

    private function isAuthorized(Token $token, array $authorizeConfig): bool
    {
        $result = true;

        foreach ($authorizeConfig as $claim) {
            if (!$token->hasClaim($claim)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    private function getAuthorizeConfiguration(Request $request): array
    {
        $url = $request->getUriString();

        if (isset($this->routesConfig[$url])) {
            if (isset($this->routesConfig[$url]['options']['defaults']['authorize'])) {
                return $this->routesConfig[$url]['options']['defaults']['authorize'];
            }
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

        if (!is_null($headers))
        {
            $token = $headers->toString();
            $token = str_replace('Authorization: Bearer', null, $token);

            return $token;
        }

        throw new BasicAuthorizationException('Authorization exception.');
    }
}