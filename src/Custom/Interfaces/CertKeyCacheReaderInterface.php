<?php

namespace Zend\Mvc\OIDC\Custom\Interfaces;

/**
 * Interface CertKeyCacheReaderInterface
 *
 * @package Zend\Mvc\OIDC\Custom\Interfaces
 */
interface CertKeyCacheReaderInterface
{
    /**
     * @param string $id
     *
     * @return string|null
     */
    public function read(string $id): ?string;
}