<?php

namespace Jmikola\Tests\WildcardEventDispatcher;

use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

class WildcardEventDispatcherFunctionalTest extends TestCase
{
    const coreRequest = 'core.request';
    const coreException = 'core.exception';
    const apiRequest = 'api.request';
    const apiException = 'api.exception';

    private $dispatcher;
    private $listener;

    public function setUp(): void
    {
        $this->dispatcher = new WildcardEventDispatcher();
        $this->listener = new TestEventListener();
    }

    public function testInitialState()
    {
        $this->assertEquals([], $this->dispatcher->getListeners());
        $this->assertFalse($this->dispatcher->hasListeners(self::coreRequest));
        $this->assertFalse($this->dispatcher->hasListeners(self::coreException));
        $this->assertFalse($this->dispatcher->hasListeners(self::apiRequest));
        $this->assertFalse($this->dispatcher->hasListeners(self::apiException));
    }

    public function testAddingAndRemovingListeners()
    {
        $this->dispatcher->addListener('#', [$this->listener, 'onAny']);
        $this->dispatcher->addListener('core.*', [$this->listener, 'onCore']);
        $this->dispatcher->addListener('*.exception', [$this->listener, 'onException']);
        $this->dispatcher->addListener(self::coreRequest, [$this->listener, 'onCoreRequest']);

        $this->assertNumberListenersAdded(3, self::coreRequest);
        $this->assertNumberListenersAdded(3, self::coreException);
        $this->assertNumberListenersAdded(1, self::apiRequest);
        $this->assertNumberListenersAdded(2, self::apiException);
        $this->assertNumberListenersAdded(9);

        $this->dispatcher->removeListener('#', [$this->listener, 'onAny']);

        $this->assertNumberListenersAdded(2, self::coreRequest);
        $this->assertNumberListenersAdded(2, self::coreException);
        $this->assertNumberListenersAdded(0, self::apiRequest);
        $this->assertNumberListenersAdded(1, self::apiException);
        $this->assertNumberListenersAdded(5);

        $this->dispatcher->removeListener('core.*', [$this->listener, 'onCore']);

        $this->assertNumberListenersAdded(1, self::coreRequest);
        $this->assertNumberListenersAdded(1, self::coreException);
        $this->assertNumberListenersAdded(0, self::apiRequest);
        $this->assertNumberListenersAdded(1, self::apiException);
        $this->assertNumberListenersAdded(3);

        $this->dispatcher->removeListener('*.exception', [$this->listener, 'onException']);

        $this->assertNumberListenersAdded(1, self::coreRequest);
        $this->assertNumberListenersAdded(0, self::coreException);
        $this->assertNumberListenersAdded(0, self::apiRequest);
        $this->assertNumberListenersAdded(0, self::apiException);
        $this->assertNumberListenersAdded(1);

        $this->dispatcher->removeListener(self::coreRequest, [$this->listener, 'onCoreRequest']);

        $this->assertNumberListenersAdded(0, self::coreRequest);
        $this->assertNumberListenersAdded(0, self::coreException);
        $this->assertNumberListenersAdded(0, self::apiRequest);
        $this->assertNumberListenersAdded(0, self::apiException);
        $this->assertNumberListenersAdded(0);
    }

    public function testAddedListenersWithWildcardsAreRegisteredLazily()
    {
        $this->dispatcher->addListener('#', [$this->listener, 'onAny']);

        $this->assertNumberListenersAdded(0);

        $this->assertTrue($this->dispatcher->hasListeners(self::coreRequest));
        $this->assertNumberListenersAdded(1, self::coreRequest);
        $this->assertNumberListenersAdded(1);

        $this->assertTrue($this->dispatcher->hasListeners(self::coreException));
        $this->assertNumberListenersAdded(1, self::coreException);
        $this->assertNumberListenersAdded(2);

        $this->assertTrue($this->dispatcher->hasListeners(self::apiRequest));
        $this->assertNumberListenersAdded(1, self::apiRequest);
        $this->assertNumberListenersAdded(3);

        $this->assertTrue($this->dispatcher->hasListeners(self::apiException));
        $this->assertNumberListenersAdded(1, self::apiException);
        $this->assertNumberListenersAdded(4);
    }

    public function testDispatch()
    {
        $this->dispatcher->addListener('#', [$this->listener, 'onAny']);
        $this->dispatcher->addListener('core.*', [$this->listener, 'onCore']);
        $this->dispatcher->addListener('*.exception', [$this->listener, 'onException']);
        $this->dispatcher->addListener(self::coreRequest, [$this->listener, 'onCoreRequest']);

        $this->dispatcher->dispatch(new Event(), self::coreRequest);
        $this->dispatcher->dispatch(new Event(), self::coreException);
        $this->dispatcher->dispatch(new Event(), self::apiRequest);
        $this->dispatcher->dispatch(new Event(), self::apiException);

        $this->assertEquals(4, $this->listener->onAnyInvoked);
        $this->assertEquals(2, $this->listener->onCoreInvoked);
        $this->assertEquals(1, $this->listener->onCoreRequestInvoked);
        $this->assertEquals(2, $this->listener->onExceptionInvoked);
    }

    /**
     * Asserts the number of listeners added for a specific event or all events
     * in total.
     *
     * @param integer $expected
     * @param string  $eventName
     */
    private function assertNumberListenersAdded($expected, $eventName = null)
    {
        return isset($eventName)
            ? $this->assertCount($expected, $this->dispatcher->getListeners($eventName))
            : $this->assertEquals($expected, array_sum(array_map('count', $this->dispatcher->getListeners())));
    }
}

class TestEventListener
{
    public $onAnyInvoked = 0;
    public $onCoreInvoked = 0;
    public $onCoreRequestInvoked = 0;
    public $onExceptionInvoked = 0;

    public function onAny(Event $e)
    {
        ++$this->onAnyInvoked;
    }

    public function onCore(Event $e)
    {
        ++$this->onCoreInvoked;
    }

    public function onCoreRequest(Event $e)
    {
        ++$this->onCoreRequestInvoked;
    }

    public function onException(Event $e)
    {
        ++$this->onExceptionInvoked;
    }
}
