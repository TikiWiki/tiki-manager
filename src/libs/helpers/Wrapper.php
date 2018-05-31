<?php

class Wrapper
{
    private $__wrapped_object;
    private $__wrapped_properties;
    private $__wrapped_methods;

    public function __construct($obj, $props=array(), $methods=array())
    {
        $this->__wrapped_object = $obj;
        $this->__wrapped_properties = $props ?: array();
        $this->__wrapped_methods = $methods ?: array();
    }

    public function __call($name, $arguments=array())
    {
        $arguments = is_array($arguments)
            ? $arguments
            : array();

        $call = array($this->__wrapped_object, $name);

        if(isset($this->__wrapped_methods[$name])
            && is_callable($this->__wrapped_methods[$name])) {
            $call = $this->__wrapped_methods[$name];
        }

        return call_user_func_array($call, $arguments);
    }

    public function __get($name)
    {
        if(isset($this->__wrapped_properties[$name])) {
            return $this->__wrapped_properties[$name];
        }
        return $this->__wrapped_object->{$name};
    }

    public function __isset($name)
    {
        return isset($this->__wrapped_properties[$name])
            || property_exists($this->__wrapped_object, $name);
    }

    public function __set($name, $value)
    {
        $this->__wrapped_properties[$name] = $value;
        return $value;
    }

    public function __unset($name)
    {
        unset($this->__wrapped_properties[$name]);
        unset($this->__wrapped_object->{$name});
    }
}
