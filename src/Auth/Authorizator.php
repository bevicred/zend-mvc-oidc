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
     * Authorizator constructor.
     *
     * @param array $config
     * @param ServiceLocatorInterface $serviceManager
     *
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    public function __construct(array $config, ServiceLocatorInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;

        $this->configurationParser = new ConfigurationParser();
        $this->configuration = $this->configurationParser->parse($config);
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool
     * @throws BasicAuthorizationException
     */
    public function authorize(RequestInterface $request): bool
    {
        $headerToken = $this->getAuthorizationToken($request);

        $token = new Token($headerToken);
        $result = $token->validate();



        return true;
    }

    /**
     * @param Request $request
     *
     * @return string
     * @throws BasicAuthorizationException
     */
    private function getAuthorizationToken(Request $request): string
    {
        $headers = $request->getHeaders('Authorization: Bearer', null);
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