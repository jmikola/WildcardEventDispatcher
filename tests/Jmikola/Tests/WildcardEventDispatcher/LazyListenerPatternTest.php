<?php

namespace Jmikola\Tests\WildcardEventDispatcher;

use Jmikola\WildcardEventDispatcher\LazyListenerPattern;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class LazyListenerPatternTest extends TestCase
{
    public function testConstructorRequiresListenerProviderCallback()
    {
        $this->expectException(InvalidArgumentException::class);
        new LazyListenerPattern('*', null);
    }

    public function testLazyListenerInitialization()
    {
        $listenerProviderInvoked = 0;

        $listenerProvider = function() use (&$listenerProviderInvoked) {
            ++$listenerProviderInvoked;
            return 'callback';
        };

        $pattern = new LazyListenerPattern('*', $listenerProvider);

        $this->assertEquals(0, $listenerProviderInvoked, 'The listener provider should not be invoked until the listener is requested');
        $this->assertEquals('callback', $pattern->getListener());
        $this->assertEquals(1, $listenerProviderInvoked, 'The listener provider should be invoked when the listener is requested');
        $this->assertEquals('callback', $pattern->getListener());
        $this->assertEquals(1, $listenerProviderInvoked, 'The listener provider should only be invoked once');
    }
}
