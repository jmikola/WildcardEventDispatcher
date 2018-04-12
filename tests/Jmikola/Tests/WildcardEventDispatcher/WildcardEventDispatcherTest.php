<?php

namespace Jmikola\Tests\WildcardEventDispatcher;

use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private $dispatcher;
    private $innerDispatcher;

    public function setUp()
    {
        $this->innerDispatcher = $this->getMockEventDispatcher();
        $this->dispatcher = new WildcardEventDispatcher($this->innerDispatcher);
    }

    /**
     * @dataProvider provideListenersWithoutWildcards
     */
    public function testShouldAddListenersWithoutWildcardsEagerly($eventName, $listener, $priority)
    {
        $this->innerDispatcher->expects($this->once())
            ->method('addListener')
            ->with($eventName, $listener, $priority);

        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function provideListenersWithoutWildcards()
    {
        return array(
            array('core.request', 'callback', 0),
            array('core.exception', array('class', 'method'), 5),
        );
    }

    /**
     * @dataProvider provideListenersWithWildcards
     */
    public function testShouldAddListenersWithWildcardsLazily($eventName, $listener, $priority)
    {
        $this->innerDispatcher->expects($this->never())
            ->method('addListener');

        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function provideListenersWithWildcards()
    {
        return array(
            array('core.*', 'callback', 0),
            array('#', array('class', 'method'), -10),
        );
    }

    public function testShouldAddListenersWithWildcardsWhenMatchingEventIsDispatched()
    {
        $this->innerDispatcher->expects($this->once())
            ->id('listener-is-added')
            ->method('addListener')
            ->with('core.request', 'callback', 0);

        $this->innerDispatcher->expects($this->once())
            ->after('listener-is-added')
            ->method('dispatch')
            ->with('core.request');

        $this->dispatcher->addListener('core.*', 'callback', 0);
        $this->dispatcher->dispatch('core.request');
    }

    public function testShouldAddListenersWithWildcardsWhenListenersForMatchingEventsAreRetrieved()
    {
        $this->innerDispatcher->expects($this->once())
            ->id('listener-is-added')
            ->method('addListener')
            ->with('core.request', 'callback', 0);

        $this->innerDispatcher->expects($this->once())
            ->after('listener-is-added')
            ->method('getListeners')
            ->with('core.request')
            ->will($this->returnValue(array('callback')));

        $this->dispatcher->addListener('core.*', 'callback', 0);

        $this->assertEquals(array('callback'), $this->dispatcher->getListeners('core.request'));
    }

    public function testShouldNotCountWildcardListenersThatHaveNeverBeenMatchedWhenAllListenersAreRetrieved()
    {
        /* When getListeners() is called without an event name, it attempts to
         * return the collection of listeners for all events it knows about.
         * When working with wildcards, we cannot anticipate events until we
         * encounter a matching name. Therefore, getListeners() will ignore any
         * wildcard listeners that are registered but haven't matched anything.
         */
        $this->innerDispatcher->expects($this->never())
            ->method('addListener');

        $this->innerDispatcher->expects($this->any())
            ->method('getListeners')
            ->will($this->returnValue(array()));

        $this->dispatcher->addListener('core.*', 'callback', 0);
        $this->assertEquals(array(), $this->dispatcher->getListeners());
    }

    public function testAddingAndRemovingAnEventSubscriber()
    {
        /* Since the EventSubscriberInterface defines getSubscribedEvents() as
         * static, we cannot mock it with PHPUnit and must use a stub class.
         */
        $subscriber = new TestEventSubscriber();

        $this->innerDispatcher->expects($this->at(0))
            ->method('addListener')
            ->with('core.request', array($subscriber, 'onRequest'), 0);
        $this->innerDispatcher->expects($this->at(1))
            ->method('addListener')
            ->with('core.exception', array($subscriber, 'onException'), 10);
        $this->innerDispatcher->expects($this->at(2))
            ->method('addListener')
            ->with('core.multi', array($subscriber, 'onMulti1'), 10);
        $this->innerDispatcher->expects($this->at(3))
            ->method('addListener')
            ->with('core.multi', array($subscriber, 'onMulti2'), 20);

        $this->innerDispatcher->expects($this->at(4))
            ->method('removeListener')
            ->with('core.request', array($subscriber, 'onRequest'));
        $this->innerDispatcher->expects($this->at(5))
            ->method('removeListener')
            ->with('core.exception', array($subscriber, 'onException'));
        $this->innerDispatcher->expects($this->at(6))
            ->method('removeListener')
            ->with('core.multi', array($subscriber, 'onMulti1'));
        $this->innerDispatcher->expects($this->at(7))
            ->method('removeListener')
            ->with('core.multi', array($subscriber, 'onMulti2'));


        $this->dispatcher->addSubscriber($subscriber);
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function testGetListenerPriorityInvokesMethodOnInnerDispather()
    {
        if ( ! method_exists('Symfony\Component\EventDispatcher\EventDispatcherInterface', 'getListenerPriority')) {
            $this->markTestSkipped('getListenerPriority() does not exist on EventDispatcherInterface');
        }

        $this->innerDispatcher->expects($this->once())
            ->method('getListenerPriority')
            ->with('core.request', 'callback')
            ->will($this->returnValue(1));

        $this->assertSame(1, $this->dispatcher->getListenerPriority('core.request', 'callback'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetListenerPriorityRequiresEventNameWithoutWildcards()
    {
        $this->dispatcher->getListenerPriority('core.*', 'callback');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testGetListenerPriorityRequiresMethodOnInnerDispather()
    {
        if (method_exists('Symfony\Component\EventDispatcher\EventDispatcherInterface', 'getListenerPriority')) {
            $this->markTestSkipped('getListenerPriority() exists on EventDispatcherInterface');
        }

        $this->dispatcher->getListenerPriority('core.request', 'callback');
    }

    private function getMockEventDispatcher()
    {
        return $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
    }
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'core.request' => 'onRequest',
            'core.exception' => array('onException', 10),
            'core.multi' => array(array('onMulti1', 10), array('onMulti2', 20)),
        );
    }
}
