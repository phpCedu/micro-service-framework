<?php
namespace MSF\Encoder;

class JsonEncoder implements \MSF\EncoderInterface {
    public function encode($data) {
        return json_encode($data);
    }
    public function decode($data) {
        return json_decode($data);
    }
}

