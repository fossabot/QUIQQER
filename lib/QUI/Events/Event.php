<?php

/**
 * This file contains \QUI\Events\Event
 */

namespace QUI\Events;

use QUI;

/**
 * Events Handling
 * Extends a class with the events interface
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @licence For copyright and license information, please view the /README.md
 */
class Event implements QUI\Interfaces\Events
{
    /**
     * Registered events
     *
     * @var array
     */
    protected $_events = array();

    /**
     * @var array
     */
    protected $_currentRunning = array();

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Interfaces\Events::getList()
     */
    public function getList()
    {
        return $this->_events;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Interfaces\Events::addEvent()
     *
     * @param string   $event - The type of event (e.g. 'complete').
     * @param callback $fn    - The function to execute.
     */
    public function addEvent($event, $fn)
    {
        $this->_events[$event][] = $fn;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Interfaces\Events::addEvents()
     *
     * @param array $events
     */
    public function addEvents(array $events)
    {
        foreach ($events as $event => $fn) {
            $this->addEvent($event, $fn);
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Interfaces\Events::removeEvent()
     *
     * @param string        $event - The type of event (e.g. 'complete').
     * @param callback|boolean $fn    - (optional) The function to remove.
     */
    public function removeEvent($event, $fn = false)
    {
        if (!isset($this->_events[$event])) {
            return;
        }

        if (!$fn) {
            unset($this->_events[$event]);

            return;
        }

        foreach ($this->_events[$event] as $k => $_fn) {
            if ($_fn == $fn) {
                unset($this->_events[$event][$k]);
            }
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Interfaces\Events::removeEvents()
     *
     * @param array $events - (optional) If not passed removes all events of all types.
     */
    public function removeEvents(array $events)
    {
        foreach ($events as $event => $fn) {
            $this->removeEvent($event, $fn);
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Interfaces\Events::fireEvent()
     *
     * @param string     $event   - The type of event (e.g. 'onComplete').
     * @param array|boolean $args    - (optional) the argument(s) to pass to the function.
     *                            The arguments must be in an array.
     * @param boolean       $force   - (optional) no recursion check, optional, default = false
     *
     * @return array - Event results, assoziative array
     *
     * @throws QUI\ExceptionStack
     */
    public function fireEvent($event, $args = false, $force = false)
    {
        $results = array();

        if (strpos($event, 'on') !== 0) {
            $event = 'on'.ucfirst($event);
        }


        // recursion check
        if (isset($this->_currentRunning[$event])
            && $this->_currentRunning[$event]
            && $force === false
        ) {
            return $results;
        }

        if (!isset($this->_events[$event])) {
            return $results;
        }

        $this->_currentRunning[$event] = true;

        $Stack = new QUI\ExceptionStack();

        // execute events
        foreach ($this->_events[$event] as $fn) {

            try {
                if (!is_string($fn)) {
                    if ($args === false) {
                        $fn();
                        continue;
                    }

                    call_user_func_array($fn, $args);
                    continue;
                }

                $fn = preg_replace('/[\\\\]{2,}/', '\\', $fn);

                if ($args === false) {
                    $results[$fn] = call_user_func($fn);
                    continue;
                }

                $results[$fn] = call_user_func_array($fn, $args);

            } catch (QUI\Exception $Exception) {

                $message = $Exception->getMessage();

                if (is_string($fn)) {
                    $message .= ' :: '. $fn;
                }

                $Clone = new QUI\Exception(
                    $message,
                    $Exception->getCode(),
                    array('trace' => $Exception->getTraceAsString())
                );

                $Stack->addException($Clone);

            } catch (\Exception $Exception) {

                $message = $Exception->getMessage();

                if (is_string($fn)) {
                    $message .= ' :: '. $fn;
                }

                $Clone = new QUI\Exception(
                    $message,
                    $Exception->getCode(),
                    array('trace' => $Exception->getTraceAsString())
                );

                $Stack->addException($Clone);
            }
        }

        $this->_currentRunning[$event] = false;

        if (!$Stack->isEmpty()) {
            throw $Stack;
        }

        return $results;
    }
}
