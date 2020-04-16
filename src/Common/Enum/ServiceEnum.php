<?php

namespace Zend\Mvc\OIDC\Common\Enum;

interface ServiceEnum
{
    const CERT_KEY_CACHE_READER = 'Zend\Mvc\OIDC\Custom\CertKeyCacheReaderInterface';
    const CERT_KEY_CACHE_WRITER = 'Zend\Mvc\OIDC\Custom\CertKeyCacheWriterInterface';
    const AUTH_RESULT_HANDLER = 'Zend\Mvc\OIDC\Custom\AuthResultHandlerInterface';
}