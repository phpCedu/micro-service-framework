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
    public function readResponse() {
        $response = new \MSF\Response\HTTPResponse();
        if ($this->data) {
        } elseif ($this->socket) {
        }

        if (!$this->data) {
            throw new \Exception('No response data to read');
        }

        // Split headers and body
        list($headers, $body) = explode("\r\n\r\n", $this->data);
        if (!$body) {
            throw new \Exception('Response body is empty');
        }
        $response->encoded = $body;
        foreach (explode("\r\n", $headers) as $header) {
            if (strncasecmp($header, 'HTTP_Z_', 7) == 0) {
                $colon = strpos($header, ':');
                $key = strtolower(trim(substr($header, 7, $colon-7)));
                $value = trim(substr($header, $colon+1));
                $response->oob($key, json_decode($value, true));
            }
        }
        $response->decodeUsing($this->encoder);
        return $response;
    }

    /*
    // TODO - Not implemented yet
    public function writeRequest(\MSF\Request $request) {
        // Write out $this->headers
        // Now write out the response annotations as headers
        $this->writeOOB($request->oob());
       
        // Now write the response body
        // fwrite($this->socket, $request->encoded);
        // 
        return 0;
    }
    */

    public function newRequest() {
        return new \MSF\Request\HTTPRequest();
    }
    public function newResponse() {
        return new \MSF\Response\HTTPResponse();
    }

    protected function writeOOB($oob) {
    }
}

