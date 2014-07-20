<?php
namespace MSF\Helper;

abstract class OnionProxy {
    protected $parent;

    public function __construct($parent = null) {
        $this->parent = $parent;
    }

    /*
    Think it's desirable for request objects to be wrapped in layers like an onion.
    Otherwise, when converting a BaseRequest to HTTPRequest, we'd have to clone all the data from Base into HTTP, which is error prone.
    Better to simply wrap it, allow member variable accesses and method calls on HTTPRequest to take precedence, deferring to BaseRequest
    as necessary.
    */
    public function __call($name, $args) {
        // Attempt to call this method on the parent
        if ($this->parent) {
            // call and return, bla
        }
    }
    public function __get($name) {
        // Proxy up to parent in same manner as __call
        if (isset($this->$name)) {
            return $this->$name;
        } elseif ($this->parent) {
            return $this->parent->$name;
        }
    }
    public function __set($name, $value) {
        // Need some checks here, to make sure we're only setting protected variables
        $this->$name = $value;
    }
}
