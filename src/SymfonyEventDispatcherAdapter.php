<?php
namespace Duktig\Event\Dispatcher\Adapter\SymfonyEventDispatcher;

use Duktig\Core\Event\Dispatcher\EventDispatcherInterface;
use Duktig\Core\Event\EventInterface;
use Duktig\Core\Event\ListenerInterface;
use Duktig\Core\DI\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Duktig\Core\Exception\ContainerServiceNotFound;

class SymfonyEventDispatcherAdapter implements EventDispatcherInterface
{
    private $dispatcher;
    private $resolver;
    
    public function __construct(SymfonyEventDispatcher $dispatcher, 
        ContainerInterface $resolver)
    {
        $this->dispatcher = $dispatcher;
        $this->resolver = $resolver;
    }
    
    /**
     * {@inheritDoc}
     * @see \Duktig\Core\Event\Dispatcher\EventDispatcherInterface::getResolver()
     */
    public function getResolver() : ContainerInterface
    {
        return $this->resolver;
    }
    
    /**
     * {@inheritDoc}
     * @see \Duktig\Core\Event\Dispatcher\EventDispatcherInterface::addListener()
     */
    public function addListener(string $eventName, $listener) : void
    {
        $this->dispatcher->addListener($eventName, $listener);
    }
    
    /**
     * {@inheritDoc}
     * @see \Duktig\Core\Event\Dispatcher\EventDispatcherInterface::dispatch()
     * 
     * @throws \InvalidArgumentException If an invalid listener was provided
     */
    public function dispatch(EventInterface $event) : void
    {
        $eventName = $event->getName();
        $listeners = $this->dispatcher->getListeners($eventName);
        foreach ($listeners as $listener) {
            if (is_callable($listener)) {
                try {
                    $listener($event);
                } catch (\Throwable $e) {
                    throw new \InvalidArgumentException(
                        "Invalid callable as listener provided for event '{$eventName}',"
                        ." and it cannot be executed",
                        null,
                        $e
                    );
                }
            } else {
                try {
                    $listenerInstance = $this->getResolver()->get($listener);
                } catch (\Throwable $e) {
                    throw new ContainerServiceNotFound(
                        "Invalid service as listener '{$listener}' provided for event"
                        ." '{$eventName}', and it cannot be resolved",
                        null,
                        $e
                    );
                }
                if (!$listenerInstance instanceof ListenerInterface) {
                    throw new \InvalidArgumentException(
                        "Invalid service as listener '{$listener}' provided for event"
                        ." '{$eventName}', expected \Duktig\Core\Event\ListenerInterface"
                    );
                }
                $listenerInstance->handle($event);
            }
        }
    }
}