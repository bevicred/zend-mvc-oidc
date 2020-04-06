<?php

use Zend\Router\Http\Literal;

return [
    'listeners' => [
        "Zend\Mvc\OIDC\Listener\OidcAuthListener"
    ],
    'service_manager' => [
        'factories' => [
            "Zend\Mvc\OIDC\Listener\OidcAuthListener" => \Zend\Mvc\OIDC\Factory\OidcAuthEventListenerFactory::class
        ]
    ]
];