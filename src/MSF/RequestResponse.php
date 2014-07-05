<?php
namespace MSF;

class RequestResponse {
    // RPC related
    protected $rpc; // An array with class and method
    protected $args;
    protected $errors;
    // Body
    protected $body;
    protected $encoded; // Transports only read/write encoded values
    // Stuff to make Filters easier
    protected $parent;

    protected $response;

    public function __construct($parent = null) {
        $this->parent = $parent;
    }

    // Encode/Decode this request/response using the specified encoder 
    public function encodeUsing(\MSF\EncoderInterface $encoder, $request = false) {
        $data = array(
            'rpc' => $this->rpc,
            'args' => $this->args
        );
        if (!$request) {
            if ($this->errors) {
                $data['errors'] = $this->errors;
            } else {
                $data['body'] = $this->body;
            }
        }
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing(\MSF\EncoderInterface $encoder) {
        $data = $encoder->decode($this->encoded);
        // Do we need to store the decoded body in $request->body?
        // Request values get annotated with the RPC call, arguments, etc
        $this->rpc = $data['rpc'];
        $this->args = $data['args'];
        if (array_key_exists('errors', $data)) {
            $this->errors = $data['errors'];
        } elseif (array_key_exists('body', $data)) {
            $this->body = $data['body'];
        }
    }

    public function oob($key = null, $value = null) {
        // RequestResponse doesn't support OOB
        return array();
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

