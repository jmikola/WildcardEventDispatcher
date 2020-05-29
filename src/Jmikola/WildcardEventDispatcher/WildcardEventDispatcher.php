<?php

namespace Jmikola\WildcardEventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use InvalidArgumentException;

class WildcardEventDispatcher implements EventDispatcherInterface
{
    private $dispatcher;
    private $patterns = [];
    private $syncedEvents = [];

    /**
     * Constructor.
     *
     * If an EventDispatcherInterface is not provided, a new EventDispatcher
     * will be composed.
     *
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
    }

    /**
     * @see EventDispatcherInterface::dispatch()
     */
    public function dispatch($event, string $eventName = null): object
    {
        $this->bindPatterns($eventName);

        return $this->dispatcher->dispatch($event, $eventName);
    }

    /**
     * @see EventDispatcherInterface::getListeners()
     */
    public function getListeners($eventName = null)
    {
        if (null !== $eventName) {
            $this->bindPatterns($eventName);

            return $this->dispatcher->getListeners($eventName);
        }

        /* Ensure that any patterns matching a known event name are bound. If
         * we don't this this, it's possible that getListeners() could return
         * different values due to lazy listener registration.
         */
        foreach (array_keys($this->dispatcher->getListeners()) as $eventName) {
            $this->bindPatterns($eventName);
        }

        return $this->dispatcher->getListeners();
    }

    /**
     * @see EventDispatcherInterface::hasListeners()
     */
    public function hasListeners($eventName = null)
    {
        return (boolean) count($this->getListeners($eventName));
    }

    /**
     * @see EventDispatcherInterface::addListener()
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        return $this->hasWildcards($eventName)
            ? $this->addListenerPattern(new ListenerPattern($eventName, $listener, $priority))
            : $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * @see EventDispatcherInterface::removeListener()
     */
    public function removeListener($eventName, $listener)
    {
        return $this->hasWildcards($eventName)
            ? $this->removeListenerPattern($eventName, $listener)
            : $this->dispatcher->removeListener($eventName, $listener);
    }

    /**
     * @see EventDispatcherInterface::addSubscriber()
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->addListener($eventName, [$subscriber, $params]);
            } elseif (is_string($params[0])) {
                $this->addListener($eventName, [$subscriber, $params[0]], $params[1] ?? 0);
            } else {
                foreach ($params as $listener) {
                    $this->addListener($eventName, [$subscriber, $listener[0]], $listener[1] ?? 0);
                }
            }
        }
    }

    /**
     * @see EventDispatcherInterface::removeSubscriber()
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (is_array($params) && is_array($params[0])) {
                foreach ($params as $listener) {
                    $this->removeListener($eventName, [$subscriber, $listener[0]]);
                }
            } else {
                $this->removeListener($eventName, [$subscriber, is_string($params) ? $params : $params[0]]);
            }
        }
    }

    /**
     * Checks whether a string contains any wildcard characters.
     *
     * @param string $subject
     * @return boolean
     */
    protected function hasWildcards($subject)
    {
        return false !== strpos($subject, '*') || false !== strpos($subject, '#');
    }

    /**
     * Binds all patterns that match the specified event name.
     *
     * @param string $eventName
     */
    protected function bindPatterns($eventName)
    {
        if (isset($this->syncedEvents[$eventName])) {
            return;
        }

        foreach ($this->patterns as $eventPattern => $patterns) {
            foreach ($patterns as $pattern) {
                if ($pattern->test($eventName)) {
                    $pattern->bind($this->dispatcher, $eventName);
                }
            }
        }

        $this->syncedEvents[$eventName] = true;
    }

    /**
     * Adds an event listener for all events matching the specified pattern.
     *
     * This method will lazily register the listener when a matching event is
     * dispatched.
     *
     * @param ListenerPattern $pattern
     */
    protected function addListenerPattern(ListenerPattern $pattern)
    {
        $this->patterns[$pattern->getEventPattern()][] = $pattern;

        foreach ($this->syncedEvents as $eventName => $_) {
            if ($pattern->test($eventName)) {
                unset($this->syncedEvents[$eventName]);
            }
        }
    }

    /**
     * Removes an event listener from any events to which it was applied due to
     * pattern matching.
     *
     * This method cannot be used to remove a listener from a pattern that was
     * never registered.
     *
     * @param string   $eventPattern
     * @param callback $listener
     */
    protected function removeListenerPattern($eventPattern, $listener)
    {
        if (!isset($this->patterns[$eventPattern])) {
            return;
        }

        foreach ($this->patterns[$eventPattern] as $key => $pattern) {
            if ($listener == $pattern->getListener()) {
                $pattern->unbind($this->dispatcher);
                unset($this->patterns[$eventPattern][$key]);
            }
        }
    }

    /**
     * @see EventDispatcherInterface::getListenerPriority()
     * @throws InvalidArgumentException if $eventName contains a wildcard pattern
     */
    public function getListenerPriority($eventName, $listener)
    {
        if ($this->hasWildcards($eventName)) {
            throw new InvalidArgumentException('Wildcard patterns are not supported');
        }

        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }
}
