<?php

namespace Zend\Mvc\OIDC;

/**
 * Class Module
 *
 * @package Zend\Mvc\OIDC
 */
class Module
{
    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}