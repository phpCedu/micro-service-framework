<?php
namespace MSF;

/*
Perhaps this is where RPC type checking should take place, but that requires us to define the service calls and types ...
so that'll be a work in progress.
*/
interface EncoderInterface {
    public function encode($data);
    public function decode($data);
}

