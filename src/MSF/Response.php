<?php
namespace MSF;

class Response extends \MSF\Helper\OnionProxy implements \MSF\RequestResponseInterface {
    // RPC related
    protected $rpc; // An array with class and method
    protected $args;
    public $errors = array();
    // Body
    protected $body;
    protected $encoded; // Transports only read/write encoded values

    public function encodeUsing(\MSF\EncoderInterface $encoder) {
        $data = array(
            'rpc' => $this->rpc,
            'args' => $this->args
        );
        if ($this->errors) {
            $data['errors'] = $this->errors;
        } else {
            $data['body'] = $this->body;
        }
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing(\MSF\EncoderInterface $encoder) {
        $data = $encoder->decode($this->encoded);
        // Do we need to store the decoded body in $response->body?
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

    public function addError($error) {
        $this->errors[] = $error;
    }
}

