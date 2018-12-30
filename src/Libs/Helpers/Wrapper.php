<?php

namespace TikiManager\Libs\Helpers;

class Wrapper
{
    private $wrapped_object;
    private $wrapped_properties;
    private $wrapped_methods;

    public function __construct($obj, $props = [], $methods = [])
    {
        $this->wrapped_object = $obj;
        $this->wrapped_properties = $props ?: [];
        $this->wrapped_methods = $methods ?: [];
    }

    public function __call($name, $arguments = [])
    {
        $arguments = is_array($arguments)
            ? $arguments
            : [];

        $call = [$this->wrapped_object, $name];

        if (isset($this->wrapped_methods[$name])
            && is_callable($this->wrapped_methods[$name])) {
            $call = $this->wrapped_methods[$name];
        }

        return call_user_func_array($call, $arguments);
    }

    public function __get($name)
    {
        if (isset($this->wrapped_properties[$name])) {
            return $this->wrapped_properties[$name];
        }
        return $this->wrapped_object->{$name};
    }

    public function __isset($name)
    {
        return isset($this->wrapped_properties[$name])
            || property_exists($this->wrapped_object, $name);
    }

    public function __set($name, $value)
    {
        $this->wrapped_properties[$name] = $value;
        return $value;
    }

    public function __unset($name)
    {
        unset($this->wrapped_properties[$name]);
        unset($this->wrapped_object->{$name});
    }
}
