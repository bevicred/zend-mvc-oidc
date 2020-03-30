<?php

use Zend\Router\Http\Literal;

return [
    'auth_service' => [
        'auth_service_url' => 'http://34.95.175.142:8080',
        'realmId'          => 'bvcteste',
        'client_id'        => 'demo-app',
        'public_key'       => '',
        'audience'         => 'pos-api.com'
    ],
    'router'       => [
        'routes' => [
            '/auth/login' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/auth/login',
                    'defaults' => [
                        'controller' => 'SomeController::class',
                        'action'     => 'login',
                        'authorize'  => [
                            'requireClaim' => 'user_roles',
                            'values'       => [
                                'Administrator',
                                'SpecialPerson'
                            ]
                        ]
                    ],
                ],
            ],
            'policies'    => [
                'Administrator' => [
                    'requireClaim' => 'user_roles',
                    'values'       => [
                        'read:person',
                        'write:person'
                    ]
                ]
            ],
            'whitelist'   => [
                '/login'
            ]
        ]
    ]
];