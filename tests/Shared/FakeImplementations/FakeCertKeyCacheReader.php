<?php

namespace Tests\Shared\FakeImplementations;

use Zend\Mvc\OIDC\Custom\Interfaces\CertKeyCacheReaderInterface;

/**
 * Class FakeCertKeyCacheReader
 *
 * @package Tests\Shared\FakeImplementations
 */
class FakeCertKeyCacheReader implements CertKeyCacheReaderInterface
{

    /**
     * @inheritDoc
     */
    public function read(string $id): ?string
    {
        return 'teste';
    }
}