<?php
namespace MSF\RequestResponse;

class HTTPRequestResponse extends \MSF\RequestResponse {
    // associative
    public $headers = array();

    // The idea is that we can add annotations, but not all transports have a way of supporting them, maybe?
    // We were going to encode some data in HTTP headers, so an HTTPTransport would take the annotations and convert them to headers
    protected $_oob = array();

    public function oob($key=null, $value=null) {
        if (is_null($key)) {
            return $this->_oob;
        }
        if (is_null($value)) {
            return $this->_oob[$key];
        }
        $this->_oob[$key] = $value;
    }
}

