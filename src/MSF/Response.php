<?php
namespace MSF;

class Response extends \MSF\Helper\OnionProxy implements \MSF\RequestResponseInterface {
    public $errors = array();
    protected $body;
    protected $encoded; // Transports only read/write encoded values

    public function encodeUsing(\MSF\EncoderInterface $encoder) {
        $data = array();
        if ($this->errors) {
            $data['errors'] = $this->errors;
        } else {
            $data['body'] = $this->body;
        }
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing(\MSF\EncoderInterface $encoder) {
        $data = $encoder->decode($this->encoded);
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

