<?php
namespace MSF\Transport;

class HTTPTransport extends \MSF\Transport {
    protected $socket;
    protected $data;
    
    public function socket($socket) {
        $this->socket = $socket;
    }
    public function data($data) {
        $this->data = $data;
    }
    public function read() {
        $r = new \MSF\RequestResponse\HTTPRequestResponse();
        if ($this->data) {
        } elseif ($this->socket) {
        }

        // Split headers and body
        list($headers, $body) = explode("\r\n\r\n", $this->data);
        $r->encoded = $body;
        foreach (explode("\r\n", $headers) as $header) {
            if (strncasecmp($header, 'HTTP_Z_', 7) == 0) {
                $colon = strpos($header, ':');
                $key = strtolower(trim(substr($header, 7, $colon-7)));
                $value = trim(substr($header, $colon+1));
                $r->oob($key, json_decode($value, true));
            }
        } // need to convert to key=>value too
        return $r;
    }

    public function write($r) {
        // Write out $this->headers
        // Now write out the response annotations as headers
        $this->writeOOB($r->oob());
       
        // Now write the response body
        // fwrite($this->socket, $r->encoded);
    }

    public function newRequest() {
        return new \MSF\RequestResponse\HTTPRequestResponse();
    }
    public function newResponse() {
        return new \MSF\RequestResponse\HTTPRequestResponse();
    }

    protected function writeOOB($oob) {
        foreach ($oob as $key => $value) {
            header('HTTP_Z_' . strtoupper($key) . ':' . json_encode($value));
        }
    }
}

