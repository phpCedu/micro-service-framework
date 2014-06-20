<?php
namespace MSF;

class JsonEncoder extends Encoder {
    public function encode($data) {
        return json_encode($data);
    }
    public function decode($data) {
        return json_decode($data, true);
    }
}

