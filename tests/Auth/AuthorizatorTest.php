<?php

namespace Tests\Auth;

use DateTime;
use Exception;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Shared\Module;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Common\Exceptions\AuthorizeException;
use Zend\Mvc\OIDC\Common\Exceptions\BasicAuthorizationException;
use Zend\Mvc\OIDC\Common\Exceptions\CertificateKeyException;
use Zend\Mvc\OIDC\Common\Exceptions\InvalidAuthorizationTokenException;
use Zend\Mvc\OIDC\Common\Exceptions\JwkRecoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\OidcConfigurationDiscoveryException;
use Zend\Mvc\OIDC\Common\Exceptions\RealmConfigurationException;
use Zend\Mvc\OIDC\Common\Exceptions\ServiceUrlConfigurationException;
use Zend\Mvc\OIDC\Common\Infra\HttpClient;
use Zend\Mvc\OIDC\Custom\AuthInformationProvider;
use Zend\Mvc\Service\EventManagerFactory;
use Zend\ServiceManager\ServiceManager;

/**
 * Class AuthorizatorTest
 *
 * @package Tests\Auth
 */
class AuthorizatorTest extends TestCase
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var MvcEvent
     */
    private $mvcEvent;

    /**
     * @var string
     */
    private $publicKey;

    /**
     * @var DateTime
     */
    private $now;

    /**
     * @var string
     */
    private $issuer;

    /**
     * @var string
     */
    private $audience;

    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * setUp
     */
    public function setUp()
    {
        $this->request = new Request();

        $this->serviceManager = new ServiceManager(include __DIR__ . '/../Shared/module.config.php');
        $this->serviceManager->setFactory('EventManager', new EventManagerFactory());
        $this->serviceManager->setService('Request', $this->request);
        $this->serviceManager->setService('Response', new Response());

        $this->mvcEvent = new MvcEvent();
        $this->mvcEvent->setApplication(new Application($this->serviceManager));
    }

    /**
     * @throws AuthorizeException
     * @throws BasicAuthorizationException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     * @throws ReflectionException
     */
    public function testWhenAnUnauthorizedRequestIsMade(): void
    {
        $this->expectException(AuthorizeException::class);

        $this->request->setUri('/auth/login');

        $token = $this->createJwt('CommonPerson');

        $this->request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $this->mvcEvent->setRequest($this->request);

        $module = new Module();
        $module->onDispatch($this->mvcEvent);
    }

    /**
     * @throws BasicAuthorizationException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     * @throws ReflectionException
     */
    public function testWhenAnAuthorizedRequestIsMade(): void
    {
        $success = true;

        $this->mockHttpClient();

        try {
            $this->request->setUri('/auth/login');

            $token = $this->createJwt('SpecialPerson');

            $this->request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
            $this->mvcEvent->setRequest($this->request);

            $module = new Module();
            $module->onDispatch($this->mvcEvent);
        } catch (AuthorizeException $ex) {
            $success = false;
        }

        $this->assertTrue($success);
    }

    private function mockHttpClient(): void
    {
        /** @var HttpClient|MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->withAnyParameters()
            ->willReturn(file_get_contents(__DIR__ . '/../Shared/JsonFiles/OpenIdConnectDiscoveryResult.json'));

        $this->serviceManager->setService('HttpClient', $httpClient);

    }

    /**
     * @throws AuthorizeException
     * @throws BasicAuthorizationException
     * @throws RealmConfigurationException
     * @throws ServiceUrlConfigurationException
     * @throws ReflectionException
     * @throws CertificateKeyException
     * @throws InvalidAuthorizationTokenException
     * @throws JwkRecoveryException
     * @throws OidcConfigurationDiscoveryException
     */
    public function testIfAnAuthorizedRequestIsMadeAndPutAuthInformationProviderOnServiceManager(): void
    {
        $this->request->setUri('/auth/login');

        $token = $this->createJwt('SpecialPerson');

        $this->request->getHeaders()->addHeaderLine('Authorization', 'Bearer ' . $token);
        $this->mvcEvent->setRequest($this->request);

        $module = new Module();
        $module->onDispatch($this->mvcEvent);

        $application = $this->mvcEvent->getApplication();
        $serviceManager = $application->getServiceManager();

        $result = $serviceManager->get(AuthInformationProvider::class);

        $this->assertInstanceOf(AuthInformationProvider::class, $result);

        /** @var AuthInformationProvider $authInformation */
        $authInformation = $result;
        $this->assertTrue($authInformation->hasClaim('user_roles'));
    }

    /**
     * @param string $claim
     *
     * @return Token
     * @throws Exception
     */
    private function createJwt(string $claim): Token
    {
        $this->now = new DateTime();

        $path = str_replace('\\', '/', realpath('teste.key.pub'));

        $this->publicKey = 'file://' . $path;
        $this->issuer = 'http://issuedby.com/auth/realms/teste';
        $this->audience = 'pos-api.com';

        $signer = new Sha256();
        $privateKey = new Key('file://teste.key');

        return (new Builder())
            ->issuedBy($this->issuer)
            ->issuedAt($this->now->getTimestamp())
            ->canOnlyBeUsedAfter($this->now->getTimestamp())
            ->expiresAt($this->now->getTimestamp() + 60)
            ->permittedFor($this->audience)
            ->withClaim('user_roles', $claim)
            ->withClaim('user_role2', 'teste2')
            ->withClaim('claim_array', ['teste1', 'teste3'])
            ->getToken($signer, $privateKey);
    }
}