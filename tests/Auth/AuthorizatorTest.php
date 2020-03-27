<?php

namespace Tests\Auth;

use DateTime;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PHPUnit\Framework\TestCase;
use Tests\Shared\Module;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\OIDC\Common\Exceptions\AuthorizeException;
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
     * setUp
     */
    public function setUp()
    {
        $this->request = new Request();

        $serviceManager = new ServiceManager(include __DIR__ . '/../Shared/module.config.php');
        $serviceManager->setFactory('EventManager', new EventManagerFactory());
        $serviceManager->setService('Request', $this->request);
        $serviceManager->setService('Response', new Response());

        $this->mvcEvent = new MvcEvent();
        $this->mvcEvent->setApplication(new Application($serviceManager));
    }

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

    private function createJwt(string $claim): \Lcobucci\JWT\Token
    {
        $this->now = new DateTime();

        $path = str_replace('\\', '/', realpath('teste.key.pub'));

        $this->publicKey = 'file://' . $path;
        $this->issuer = 'http://issuedby.com/auth/realms/teste';

        $signer = new Sha256();
        $privateKey = new Key('file://teste.key');

        return (new Builder())
            ->issuedBy($this->issuer)
            ->issuedAt($this->now->getTimestamp())
            ->canOnlyBeUsedAfter($this->now->getTimestamp())
            ->expiresAt($this->now->getTimestamp() + 60)
            ->withClaim('user_role', $claim)
            ->getToken($signer, $privateKey);
    }
}