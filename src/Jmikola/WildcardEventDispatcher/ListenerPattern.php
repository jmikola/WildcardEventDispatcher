<?php

namespace Jmikola\WildcardEventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListenerPattern
{
    protected $eventPattern;
    protected $events = array();
    protected $listener;
    protected $priority;
    protected $regex;

    private static $replacements;

    /**
     * Constructor.
     *
     * @param string   $eventPattern
     * @param callback $listener
     * @param integer  $priority
     */
    public function __construct($eventPattern, $listener, $priority = 0)
    {
        $this->eventPattern = $eventPattern;
        $this->listener = $listener;
        $this->priority = $priority;
        $this->regex = $this->createRegex($eventPattern);
    }

    /**
     * Get the event pattern.
     *
     * @return string
     */
    public function getEventPattern()
    {
        return $this->eventPattern;
    }

    /**
     * Get the listener.
     *
     * @return callback
     */
    public function getListener()
    {
        return $this->listener;
    }

    /**
     * Adds this pattern's listener to an event.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param string                   $eventName
     */
    public function bind(EventDispatcherInterface $dispatcher, $eventName)
    {
        if (isset($this->events[$eventName])) {
            return;
        }

        $dispatcher->addListener($eventName, $this->getListener(), $this->priority);
        $this->events[$eventName] = true;
    }

    /**
     * Removes this pattern's listener from all events to which it was
     * previously added.
     *
     * @param EventDispatcherInterface $dispatcher
     */
    public function unbind(EventDispatcherInterface $dispatcher)
    {
        foreach ($this->events as $eventName => $_) {
            $dispatcher->removeListener($eventName, $this->getListener());
        }

        $this->events = array();
    }

    /**
     * Tests if this pattern matches and event name.
     *
     * @param string $eventName
     * @return boolean
     */
    public final function test($eventName)
    {
        return (boolean) preg_match($this->regex, $eventName);
    }

    /**
     * Transforms an event pattern into a regular expression.
     *
     * @param string $eventPattern
     * @return string
     */
    private function createRegex($eventPattern)
    {
        $replacements = self::getReplacements();

        return sprintf('/^%s$/', preg_replace(
            array_keys($replacements),
            array_values($replacements),
            preg_quote($eventPattern, '/')
        ));
    }

    /**
     * Returns preg_replace() replacements for preparing the event pattern.
     *
     * @return array
     */
    private static function getReplacements()
    {
        if (null !== self::$replacements) {
            return self::$replacements;
        }

        self::$replacements = array(
            // Trailing single-wildcard with separator prefix
            '/\\\\\.\\\\\*$/'     => '(?:\.\w+)?',
            // Single-wildcard with separator prefix
            '/\\\\\.\\\\\*/'      => '(?:\.\w+)',
            // Single-wildcard without separator prefix
            '/(?<!\\\\\.)\\\\\*/' => '(?:\w+)',
        );

        // preg_quote() escapes `#` in PHP 7.3+
        if (PHP_VERSION_ID >= 70300) {
            self::$replacements += array(
                // Multi-wildcard with separator prefix
                '/\\\\\.\\\\\#/'      => '(?:\.\w+)*',
                // Multi-wildcard without separator prefix
                '/(?<!\\\\\.)\\\\\#/' => '(?:|\w+(?:\.\w+)*)',
            );
        } else {
            self::$replacements += array(
                // Multi-wildcard with separator prefix
                '/\\\\\.#/'      => '(?:\.\w+)*',
                // Multi-wildcard without separator prefix
                '/(?<!\\\\\.)#/' => '(?:|\w+(?:\.\w+)*)',
            );
        }

        return self::$replacements;
    }
}