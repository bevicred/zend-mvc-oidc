<?php

namespace Tests\Listener;

use DateTime;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
            'auth_service' => [
                'auth_service_url' => 'http://34.95.175.142:8080',
                'realmId'          => '',
                'client_id'        => 'demo-app',
                'audience'         => 'pos-api.com'
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
    public function testHandle()
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
}