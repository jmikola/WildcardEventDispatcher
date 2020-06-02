<?php

namespace Jmikola\WildcardEventDispatcher;

class LazyListenerPattern extends ListenerPattern
{
    protected $listenerProvider;

    /**
     * Constructor.
     *
     * The $listenerProvider argument should be a callable which, when invoked,
     * returns the listener callable.
     *
     * @param string   $eventPattern
     * @param callable $listenerProvider
     * @param integer  $priority
     */
    public function __construct(string $eventPattern, callable $listenerProvider, int $priority = 0)
    {
        /* Since ListenerPattern requires a callable, we'll use the getListener
         * method as a placeholder. Later, we can replace it with the actual
         * listener from the provider callable. */
        parent::__construct($eventPattern, [$this, 'getListener'], $priority);

        $this->listenerProvider = $listenerProvider;
    }

    /**
     * Get the pattern listener, initializing it lazily from its provider.
     *
     * @return callable
     */
    public function getListener(): callable
    {
        if ($this->listener === [$this, 'getListener']) {
            $this->listener = call_user_func($this->listenerProvider);
            unset($this->listenerProvider);
        }

        return $this->listener;
    }
}
