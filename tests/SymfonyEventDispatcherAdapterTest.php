<?php
namespace Duktig\Event\Dispatcher\Adapter\SymfonyEventDispatcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Duktig\Core\DI\ContainerInterface;
use Duktig\Core\Event\EventInterface;
use Duktig\Core\Event\ListenerInterface;

class SymfonyEventDispatcherAdapterTest extends TestCase
{
    private $dispatcherMock;
    private $resolverMock;
    private $adapter;
    
    public function setUp()
    {
        parent::setUp();
        $this->setDispatcher();
    }
    
    public function tearDown()
    {
        parent::tearDown();
        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        \Mockery::close();
        $this->unsetDispatcher();
    }
    
    public function testAddsListener()
    {
        $eventName = 'test.event';
        $listener = function($event){ echo 'ob fake listener'; };
        
        $this->dispatcherMock->shouldReceive('addListener')->once()
            ->with($eventName, $listener);
        
        $this->adapter->addListener($eventName, $listener);
    }
    
    public function testRunsCallableListener()
    {
        $event = \Mockery::mock(EventInterface::class, ['getName' => 'test.event']);
        $listener = function($event){ echo 'ob fake listener'; };
        
        $this->dispatcherMock->shouldReceive('getListeners')->with('test.event')
            ->once()->andReturn([$listener]);
        
        $this->adapter->dispatch($event);
        $this->expectOutputRegex('/ob fake listener/');
    }
    
    public function testThrowsExceptionForInvalidCallableListener()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid callable as listener provided for"
            ." event 'test.event', and it cannot be executed");
        
        $event = \Mockery::mock(EventInterface::class, ['getName' => 'test.event']);
        $listener = function(TypeDoesntExist $param){ echo 'ob fake listener'; };
        $this->dispatcherMock->shouldReceive('getListeners')->with('test.event')
            ->once()->andReturn([$listener]);
        
        $this->adapter->dispatch($event);
    }
    
    public function testResolverResolvesAndCallsHandleOnNonCallableListener()
    {
        $event = \Mockery::mock(EventInterface::class, ['getName' => 'test.event']);
        $listener = 'ResolvableServiceID';
        $resolvedListenerMock = \Mockery::mock(ListenerInterface::class);
        
        $this->dispatcherMock->shouldReceive('getListeners')->with('test.event')
            ->once()->andReturn([$listener]);
        $this->resolverMock->shouldReceive('get')->with($listener)
            ->andReturn($resolvedListenerMock);
        $resolvedListenerMock->shouldReceive('handle')
            ->andReturnUsing(function () { echo 'ob from fake handle'; });
        
        $this->adapter->dispatch($event);
        $this->expectOutputRegex('/ob from fake handle/');
    }
    
    public function testThrowsExceptionForUnresolvableListener()
    {
        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);
        $this->expectExceptionMessage("Invalid service as listener 'UnresolvableServiceID'"
            ." provided for event 'test.event', and it cannot be resolved");
        
        $event = \Mockery::mock(EventInterface::class, ['getName' => 'test.event']);
        $listener = 'UnresolvableServiceID';
        
        $this->dispatcherMock->shouldReceive('getListeners')->with('test.event')
            ->once()->andReturn([$listener]);
        $this->resolverMock->shouldReceive('get')->with($listener)
            ->andReturnUsing(function () { throw new \Elazar\Auryn\Exception\NotFoundException; });
        
        $this->adapter->dispatch($event);
        $this->expectOutputRegex('/ob from fake handle/');
    }
    
    public function testThrowsExceptionIfResolvedListenerIsNotAnInstanceofListenerInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid service as listener 'ResolvableServiceID'"
            ." provided for event 'test.event', expected \Duktig\Core\Event\ListenerInterface");
        
        $event = \Mockery::mock(EventInterface::class, ['getName' => 'test.event']);
        $listener = 'ResolvableServiceID';
        $resolvedInvalidListenerMock = \Mockery::mock(\stdClass::class);
        
        $this->dispatcherMock->shouldReceive('getListeners')->with('test.event')
            ->once()->andReturn([$listener]);
        $this->resolverMock->shouldReceive('get')->with($listener)
            ->andReturn($resolvedInvalidListenerMock);
        
        $this->adapter->dispatch($event);
    }
    
    private function setDispatcher()
    {
        $this->dispatcherMock = \Mockery::mock(SymfonyEventDispatcher::class);
        $this->resolverMock = \Mockery::mock(ContainerInterface::class);
        $this->adapter = new SymfonyEventDispatcherAdapter($this->dispatcherMock, $this->resolverMock);
    }
    
    private function unsetDispatcher()
    {
        unset($this->dispatcherMock, $this->resolverMock, $this->adapter);
    }
}