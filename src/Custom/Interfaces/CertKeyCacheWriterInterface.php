<?php

namespace Zend\Mvc\OIDC\Custom\Interfaces;

/**
 * Interface CertKeyCacheWriterInterface
 *
 * @package Zend\Mvc\OIDC\Custom\Interfaces
 */
interface CertKeyCacheWriterInterface
{
    /**
     * @param string $id
     * @param string $keyValue
     */
    public function write(string $id, string $keyValue): void;
}