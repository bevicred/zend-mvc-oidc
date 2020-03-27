<?php

namespace Tests\Shared;

use Zend\Mvc\OIDC\Module\AbstractModule;

/**
 * Class Module
 *
 * @package Tests\Shared
 */
class Module extends AbstractModule
{

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/module.config.php';
    }
}