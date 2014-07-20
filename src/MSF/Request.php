<?php
namespace MSF;

class Request extends \MSF\Helper\OnionProxy implements \MSF\RequestResponseInterface {
    // RPC related
    protected $rpc; // An array with class and method
    protected $args;
    // Body
    protected $body;
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
        // Do we need to store the decoded body in $request->body?
        // Request values get annotated with the RPC call, arguments, etc
        $this->rpc = $data['rpc'];
        $this->args = $data['args'];
    }

    public function oob($key = null, $value = null) {
        // Base class doesn't support OOB
        return array();
    }
}

