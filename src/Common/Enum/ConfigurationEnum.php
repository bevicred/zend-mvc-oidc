<?php

namespace Zend\Mvc\OIDC\Common\Enum;

/**
 * Interface ConfigurationEnum
 *
 * @package Zend\Mvc\OIDC\Common\Enum
 */
interface ConfigurationEnum
{
    const AUTH_SERVICE = 'zend_mvc_oidc';
    const AUTH_SERVICE_URL = 'auth_service_url';
    const REALM_ID = 'realmId';
    const CLIENT_ID = 'client_id';
    const AUDIENCE = 'audience';

    const EXCEPTION_MAPPING = 'exception_mapping';
    const INVALID_TOKEN = 'invalid_token';
    const EXPIRED_TOKEN = 'expired_token';
    const FORBIDDEN_TOKEN = 'forbidden_token';

    const AUTHORIZE_CONFIG = 'authorize';
    const REQUIRE_CLAIM = 'requireClaim';
    const ALLOW_ANONYMOUS = 'allowAnonymous';
}