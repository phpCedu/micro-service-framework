<?php
namespace MSF;

class Response extends \MSF\Helper\OnionProxy {
    public $errors = array();
    protected $body;
    protected $encoded; // Transports only read/write encoded values

    public function encodeUsing(\MSF\EncoderInterface $encoder) {
        $data = new \stdClass();
        if ($this->errors) {
            $data->errors = $this->errors;
        } else {
            $data->body = $this->body;
        }
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing(\MSF\EncoderInterface $encoder) {
        $data = $encoder->decode($this->encoded);
        if (isset($data->errors)) {
            $this->errors = $data->errors;
        } elseif (isset($data->body)) {
            $this->body = $data->body;
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

