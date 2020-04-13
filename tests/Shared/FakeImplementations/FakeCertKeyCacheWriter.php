<?php

namespace Tests\Shared\FakeImplementations;

use Zend\Mvc\OIDC\Custom\Interfaces\CertKeyCacheWriterInterface;

/**
 * Class FakeCertKeyCacheWriter
 *
 * @package Tests\Shared\FakeImplementations
 */
class FakeCertKeyCacheWriter implements CertKeyCacheWriterInterface
{

    /**
     * @inheritDoc
     */
    public function write(string $id, string $keyValue): void
    {
        // TODO: Implement write() method.
    }
}