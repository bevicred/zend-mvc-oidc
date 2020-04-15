<?php

namespace Tests\Listener;

use DateTime;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException;
use Tests\Shared\ExternalExceptions\ExternalAuthorizationTokenException;
use Tests\Shared\ExternalExceptions\ExternalInvalidTokenException;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Common\Configuration;
use Zend\Mvc\OIDC\Common\Exceptions\AudienceConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\AuthorizeException;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\CertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Parse\ConfigurationParser;
use Zend\Mvc\OIDC\Custom\AuthInformationProvider;
use Zend\Mvc\OIDC\Listener\OidcAuthEventHandler;
use Zend\Mvc\OIDC\OpenIDConnect\CertKeyService;
use Zend\Mvc\Service\EventManagerFactory;
use Zend\Router\Http\Literal;
use Zend\ServiceManager\ServiceManager;

/**
 * Class OidcAuthEventHandlerTest
 *
 * @package Tests\Listener
 */
class OidcAuthEventHandlerTest extends TestCase
{
    /**
     * @var ConfigurationParser|MockObject
     */
    private $configurationParser;

    /**
     * @var CertKeyService|MockObject
     */
    private $certKeyService;

    /**
     * @var array
     */
    private $applicationConfig;

    /**
     * @var array
     */
    private $moduleConfig;

    public function setUp()
    {
        $this->configurationParser = $this
            ->getMockBuilder(ConfigurationParser::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->certKeyService = $this
            ->getMockBuilder(CertKeyService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->applicationConfig = [
            'zend_mvc_oidc' => [
                'auth_service_url' => 'http://34.95.175.142:8080',
                'realmId'          => '',
                'client_id'        => 'demo-app',
                'audience'         => 'pos-api.com',
                'exception_mapping' => [
                    'invalid_token' => 'Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException',
                    'expired_token' => 'Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException',
                    'forbidden_token' => 'Tests\Shared\ExternalExceptions\ExternalAuthorizationTokenException',
                ]
            ]
        ];

        $this->moduleConfig = [
            'router' => [
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
    }

    /**
     * @throws AudienceConfigurationException
     * @throws AuthorizeException
     * @throws BasicAuthorizationException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     */
    public function testHandleWithValidAuthorization()
    {
        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('SpecialPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithValidAuthorizationShouldPutAuthInformationProviderInServiceManagerContainingTokenClaims()
    {
        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('SpecialPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);

        $serviceManager = $mvcEvent->getApplication()->getServiceManager();

        $expected = $serviceManager->get(AuthInformationProvider::class);
        $this->assertNotNull($expected);
        $this->assertInstanceOf(AuthInformationProvider::class, $expected);
        /** @var AuthInformationProvider $authInformationProvicer */
        $authInformationProvicer = $expected;
        $this->assertTrue($authInformationProvicer->hasClaim('user_roles'));
        $claimValue = $authInformationProvicer->getClaim('user_roles');
        $this->assertTrue(is_string($claimValue));
        $this->assertTrue($claimValue == 'SpecialPerson');
    }

    public function testHandleWithInvalidTokenShouldThrowsInvalidAuthorizationTokenException()
    {
        $this->expectException(InvalidAuthorizationTokenException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        //$configuration->setInvalidTokenExceptionMapping('Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createInvalidJwt('SpecialPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithInvalidTokenWithExternalExceptionConfiguredShouldThrowsTheExternalException()
    {
        $this->expectException(ExternalAuthenticationTokenException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $configuration->setInvalidTokenExceptionMapping('Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createInvalidJwt('SpecialPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithExpiredTokenShouldThrowsInvalidAuthorizationTokenException()
    {
        $this->expectException(InvalidAuthorizationTokenException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createExpiredJwt('SpecialPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithExpiredTokenWithExternalExceptionConfiguredShouldThrowsTheExternalException()
    {
        $this->expectException(ExternalAuthenticationTokenException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $configuration->setExpiredTokenExceptionMapping('Tests\Shared\ExternalExceptions\ExternalAuthenticationTokenException');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createExpiredJwt('SpecialPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithoutRequiredClaimForAuthorizationShouldThrowsAuthorizeException()
    {
        $this->expectException(AuthorizeException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('CommonPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithoutRequiredClaimForAuthorizationWithExternalExceptionConfiguredShouldThrowstheExternalException()
    {
        $this->expectException(ExternalAuthorizationTokenException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $configuration->setForbiddenTokenExceptionMapping('Tests\Shared\ExternalExceptions\ExternalAuthorizationTokenException');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->once())
            ->method('resolveCertificate')
            ->willReturn($resultCert);

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('CommonPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithoutAuthorizationHeaderShouldThrowsBasicAuthorizationException()
    {
        $this->expectException(BasicAuthorizationException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->never())
            ->method('resolveCertificate');

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $this->moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('CommonPerson', $configuration);
        //$request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWithoutAuthorizeConfigurationShouldThrowsAuthorizeException()
    {
        $this->expectException(AuthorizeException::class);

        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->never())
            ->method('resolveCertificate');

        $moduleConfig = [
            'router' => [
                'routes' => [
                    '/auth/login' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/auth/login',
                            'defaults' => [
                                'controller' => 'SomeController::class',
                                'action'     => 'login'
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

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('CommonPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    public function testHandleWhenAuthorizeConfigurationAllowAnonymousShouldNotVerifyToken()
    {
        $configuration = new Configuration();
        $configuration->setAuthServiceUrl('http://issuedby.com');
        $configuration->setRealmId('teste');
        $configuration->setClientId('demo-app');
        $configuration->setAudience('pos-api.com');

        $this->configurationParser
            ->expects($this->once())
            ->method('parse')
            ->willReturn($configuration);

        $path = str_replace('\\', '/', realpath('teste.key.pub'));
        $resultCert = file_get_contents($path);

        $this->certKeyService
            ->expects($this->never())
            ->method('resolveCertificate');

        $moduleConfig = [
            'router' => [
                'routes' => [
                    '/auth/login' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/auth/login',
                            'defaults' => [
                                'controller' => 'SomeController::class',
                                'action'     => 'login',
                                'authorize' => [
                                    'allowAnonymous'
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

        $handler = new OidcAuthEventHandler(
            $this->applicationConfig,
            $moduleConfig,
            $this->configurationParser,
            $this->certKeyService
        );

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $request = new Request();

        $token = $this->createJwt('CommonPerson', $configuration);
        $request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $request->setUri('/auth/login');
        $serviceManager->setService('Request', $request);
        $serviceManager->setService('Response', new Response());

        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($request);
        $mvcEvent->setApplication(new Application($serviceManager));

        $handler->handle($mvcEvent);
    }

    private function createJwt(string $claim, Configuration $configuration): Token
    {
        $signer = new Sha256();
        $key = new Key('file://teste.key');

        $now = new DateTime('now');

        return (new Builder())
            ->issuedBy($configuration->getRealmUrl())
            ->issuedAt($now->getTimestamp())
            ->canOnlyBeUsedAfter($now->getTimestamp())
            ->expiresAt($now->getTimestamp() + 60)
            ->permittedFor($configuration->getAudience())
            ->withClaim('user_roles', $claim)
            ->getToken($signer, $key);
    }

    private function createInvalidJwt(string $claim, Configuration $configuration): Token
    {
        $signer = new Sha256();
        $key = new Key('file://teste.key');

        $now = new DateTime('now');

        return (new Builder())
            ->issuedBy('invalid issuer')
            ->issuedAt($now->getTimestamp())
            ->canOnlyBeUsedAfter($now->getTimestamp())
            ->expiresAt($now->getTimestamp() + 60)
            ->permittedFor($configuration->getAudience())
            ->withClaim('user_roles', $claim)
            ->getToken($signer, $key);
    }

    private function createExpiredJwt(string $claim, Configuration $configuration): Token
    {
        $signer = new Sha256();
        $key = new Key('file://teste.key');

        $now = new DateTime('now');

        return (new Builder())
            ->issuedBy($configuration->getRealmUrl())
            ->issuedAt($now->getTimestamp() - 60)
            ->canOnlyBeUsedAfter($now->getTimestamp() - 60)
            ->expiresAt($now->getTimestamp() - 30)
            ->permittedFor($configuration->getAudience())
            ->withClaim('user_roles', $claim)
            ->getToken($signer, $key);
    }
}