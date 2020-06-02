<?php

namespace Jmikola\Tests\WildcardEventDispatcher;

use Jmikola\WildcardEventDispatcher\LazyListenerPattern;
use PHPUnit\Framework\TestCase;

class LazyListenerPatternTest extends TestCase
{
    public function testLazyListenerInitialization()
    {
        $listenerProviderInvoked = 0;
        $listener = function() {};

        $listenerProvider = function() use (&$listenerProviderInvoked, $listener) {
            ++$listenerProviderInvoked;
            return $listener;
        };

        $pattern = new LazyListenerPattern('*', $listenerProvider);

        $this->assertEquals(0, $listenerProviderInvoked, 'The listener provider should not be invoked until the listener is requested');
        $this->assertSame($listener, $pattern->getListener());
        $this->assertEquals(1, $listenerProviderInvoked, 'The listener provider should be invoked when the listener is requested');
        $this->assertSame($listener, $pattern->getListener());
        $this->assertEquals(1, $listenerProviderInvoked, 'The listener provider should only be invoked once');
    }
}
