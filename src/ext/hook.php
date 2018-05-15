<?php

class TRIM_Hooks {
    private static $instance = null;
    private $filters = null;
    private $actions = null;

    private function __construct() {
        $this->filters = array();
        $this->actions = array();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function add(&$chain, $name, $callback, $priority) {
        if (empty($chain[ $name ])) {
            $chain[ $name ] = array();
        }
        $chain[ $name ][ ] = array($callback, $priority);
    }

    public function add_filter($name, $callback, $priority=10) {
        $this->add($this->filters, $name, $callback, $priority);
    }

    public function add_action($name, $callback, $priority=10) {
        $this->add($this->actions, $name, $callback, $priority);
    }

    private function remove(&$chain, $name, $func) {
        if (empty($chain[ $name ])) {
            return;
        }
        if (empty($func)) {
            unset($chain[ $name ]);
            return;
        }
        foreach ($chain[ $name ] as $key => $function) {
            list($callback, $priority) = $function;
            if ( $callback === $func ) {
                unset($chain[ $name ][ $key ]);
            }
        }
    }

    public function remove_filter($name, $func=null) {
        return $this->remove($this->filters, $name, $func);
    }

    public function remove_action($name, $func=null) {
        return $this->remove($this->actions, $name, $func);
    }

    public function run_filters($name, $subject=null) {
        if (empty($this->filters[ $name ])) {
            return $subject;
        }

        usort($this->filters[ $name ], function($a, $b) {
            return $a[1] === $b[1] ? 0 : ( $a[1] < $b[1] ? -1 : 1 ); 
        });

        foreach ($this->filters[ $name ] as $function) {
            list($callback, $priority) = $function;
            $subject = call_user_func($callback, $subject);
        }
        return $subject;
    }

    public function run_actions($name, $subject=null) {
        if (empty($this->actions[ $name ])) {
            return $subject;
        }

        usort($this->actions[ $name ], function($a, $b) {
            return $a[1] === $b[1] ? 0 : ( $a[1] < $b[1] ? -1 : 1 ); 
        });

        foreach ($this->actions[ $name ] as $function) {
            list($callback, $priority) = $function;
            call_user_func($callback, $subject);
        }
    }
}
