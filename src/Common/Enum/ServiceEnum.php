<?php

namespace Zend\Mvc\OIDC\Common\Enum;

interface ServiceEnum
{
    const CERT_KEY_CACHE_READER = 'Zend\Mvc\OIDC\Custom\CertKeyCacheReaderInterface';
    const CERT_KEY_CACHE_WRITER = 'Zend\Mvc\OIDC\Custom\CertKeyCacheWriterInterface';
}