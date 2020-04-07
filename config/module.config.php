<?php

use Zend\Mvc\OIDC\Factory\OidcAuthEventListenerFactory;
use Zend\Mvc\OIDC\Listener\OidcAuthListener;

return [
    'listeners' => [
        OidcAuthListener::class
    ],
    'service_manager' => [
        'factories' => [
            OidcAuthListener::class => OidcAuthEventListenerFactory::class
        ]
    ]
];