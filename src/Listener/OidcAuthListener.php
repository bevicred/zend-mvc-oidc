<?php

namespace Zend\Mvc\OIDC\Listener;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;

/**
 * Class OidcAuthListener
 *
 * @package Zend\Mvc\OIDC\Listener
 */
class OidcAuthListener extends AbstractListenerAggregate
{
    /**
     * @var OidcAuthEventHandler
     */
    private $authEventHandler;

    public function __construct(OidcAuthEventHandler $authEventHandler)
    {
        $this->authEventHandler = $authEventHandler;
    }

    /**
     * @inheritDoc
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $eventHandler = $this->authEventHandler;
        $callback = function (MvcEvent $mvcEvent) use($eventHandler) {
            $eventHandler->handle($mvcEvent);
        };

        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, $callback, 1000);
    }
}