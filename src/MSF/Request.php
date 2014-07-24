<?php
namespace MSF;

class Request extends \MSF\Helper\OnionProxy implements \MSF\RequestResponseInterface {
    protected $rpc; // String of the RPC method name
    protected $args; // An associative array
    protected $encoded; // Transports only read/write encoded values

    public function encodeUsing(\MSF\EncoderInterface $encoder) {
        $data = array(
            'rpc' => $this->rpc,
            'args' => $this->args
        );
        $this->encoded = $encoder->encode($data);
    }
    public function decodeUsing(\MSF\EncoderInterface $encoder) {
        $data = $encoder->decode($this->encoded);
        $this->rpc = $data['rpc'];
        $this->args = $data['args'];
    }

    public function oob($key = null, $value = null) {
        // Base class doesn't support OOB
        return array();
    }
}

