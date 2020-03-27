<?php

namespace Zend\Mvc\OIDC\Module;

use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\MvcEvent;

interface ModuleInterface
{
    /**
     * @return array
     */
    public function getConfig(): array;

    /**
     * @param ModuleManager $manager
     */
    public function init(ModuleManager $manager): void;

    /**
     * @param MvcEvent $event
     */
    public function onDispatch(MvcEvent $event): void;
}