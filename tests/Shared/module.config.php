<?php

use Zend\Router\Http\Literal;

return [
    'auth_service' => [
        'auth_service_url' => 'http://34.95.175.142:8080',
        'realmId' => 'bvcteste',
        'client_id' => 'demo-app',
        'public_key' => ''
    ],
    'router' => [
        'routes' => [
            '/auth/login' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/auth/login',
                    'defaults' => [
                        'controller' => 'SomeController::class',
                        'action' => 'login',
                        'authorize' => [
                            'Administrator',
                            'SpecialPerson'
                        ]
                    ],
                ],
            ],
            'whitelist' => [
                '/login'
            ]
        ]
    ]
];