<?php
namespace MSF\Encoder;

class MsgPackEncoder implements \MSF\EncoderInterface {
    public function encode($data) {
        return msgpack_pack($data);
    }
    public function decode($data) {
        return msgpack_unpack($data);
    }
}

