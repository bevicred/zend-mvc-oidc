<?php

namespace Zend\Mvc\OIDC\Module;

use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Auth\Authorizator;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;

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
        $sharedEventManager->attach($namespace, 'dispatch', [$this, 'onDispatch'], 100);
    }

    /**
     * @param MvcEvent $event
     *
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    public function onDispatch(MvcEvent $event): void
    {
        $config = $this->getConfig();

        $serviceManager = $event->getApplication()->getServiceManager();
        $request = $event->getRequest();

        $authorizator = new Authorizator($config, $serviceManager);
        $authorizator->authorize($request);
    }
}