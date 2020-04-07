<?php

namespace Zend\Mvc\OIDC\Factory;

use Interop\Container\ContainerInterface;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Infra\HttpClient;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;
use Zend\Mvc\OIDC\Listener\OidcAuthEventHandler;
use Zend\Mvc\OIDC\Listener\OidcAuthListener;
use Zend\Mvc\OIDC\OpenIDConnect\CertKeyService;
use Zend\Mvc\OIDC\OpenIDConnect\ConfigurationDiscoveryService;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Class OidcAuthEventListenerFactory
 *
 * @package Zend\Mvc\OIDC\Factory
 */
class OidcAuthEventListenerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     *
     * @return object|OidcAuthListener
     * @throws AudienceConfigurationException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $configurationDiscoveryService = new ConfigurationDiscoveryService(new HttpClient());
        $certKeyService = new CertKeyService($configurationDiscoveryService, new HttpClient());
        $configurationParser = new ConfigurationParser();

        $moduleConfig = $container->get('config');
        $applicationConfig = $container->get('ApplicationConfig');
        $oidcAuthEventHandler = new OidcAuthEventHandler(
            $applicationConfig,
            $moduleConfig,
            $configurationParser,
            $certKeyService
        );

        return new OidcAuthListener($oidcAuthEventHandler);
    }
}