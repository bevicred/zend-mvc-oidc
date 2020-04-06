<?php

namespace Zend\Mvc\OIDC\Module;

use Zend\Http\Request;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Auth\Authorizator;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\AuthorizeException;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\CertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Custom\AuthInformationProvider;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * Class AbstractModule
 *
 * @package Zend\Mvc\OIDC\Module
 */
abstract class AbstractModule implements ModuleInterface
{
    /**
     * @param ModuleManager $manager
     */
    public function init(ModuleManager $manager): void
    {
        $reflection = new \ReflectionObject($this);
        $namespace = $reflection->getNamespaceName();

        $eventManager = $manager->getEventManager();
        $sharedEventManager = $eventManager->getSharedManager();
        $sharedEventManager->attach($namespace, 'dispatch', [$this, 'onDispatch'], 1000);
    }

    /**
     * @param MvcEvent $event
     *
     * @throws AuthorizeException
     * @throws BasicAuthorizationException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     * @throws \ReflectionException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     * @throws AudienceConfigurationException
     */
    public function onDispatch(MvcEvent $event): void
    {
        $config = $this->getConfig();

        /** @var ServiceManager $serviceManager */
        $serviceManager = $event->getApplication()->getServiceManager();

        /** @var Request $request */
        $request = $event->getRequest();

        $authorizator = new Authorizator($config, $serviceManager);
        if (!$authorizator->authorize($request)) {
            throw new AuthorizeException('Authorization failed.');
        }

        $authInformationProvider = $this->createAuthInformationProvider($authorizator->getTokenClaims());
        $serviceManager->setService(AuthInformationProvider::class, $authInformationProvider);
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
        foreach ($claimsFromToken as $key=>$value){
            $outClaims[$key] = $value;
        }

        $reflection = new \ReflectionObject($authInformationProvider);
        $property = $reflection->getProperty('claims');
        $property->setAccessible(true);
        $property->setValue($authInformationProvider, $outClaims);

        return $authInformationProvider;
    }
}