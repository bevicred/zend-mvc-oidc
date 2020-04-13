<?php

namespace Zend\Mvc\OIDC\Common\Enum;

/**
 * Interface ConfigurationEnum
 *
 * @package Zend\Mvc\OIDC\Common\Enum
 */
interface ConfigurationEnum
{
    const AUTH_SERVICE = 'auth_service';
    const AUTH_SERVICE_URL = 'auth_service_url';
    const REALM_ID = 'realmId';
    const CLIENT_ID = 'client_id';
    const AUDIENCE = 'audience';

    const AUTHORIZE_CONFIG = 'authorize';
    const REQUIRE_CLAIM = 'requireClaim';
    const ALLOW_ANONYMOUS = 'allowAnonymous';
}