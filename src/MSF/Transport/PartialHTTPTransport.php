<?php
namespace MSF\Transport;

// This class is only concerned with reading the HTTP body ... it assumes the HTTP headers have already been parsed
// In the case of most PHP requests, this will be the case
class PartialHTTPTransport extends HTTPTransport {
    public function read() {
        $r = new HTTPRequestResponse();
        // headers should already be in $_SERVER, now just extract the ones that pertain to us
        // Loop through $_SERVER looking for headers with our special "HTTP_ABC123" prefix
        // $r->headers['ONE'] = 'TWO';
        
        // just read the remainder of the body
        $r->encoded = file_get_contents('php://input');
        foreach ($_SERVER as $key => $value) {
            if (strncasecmp($key, 'HTTP_Z_', 7) == 0) {
                $key = strtolower(substr($key, 7));
                $r->oob($key, json_decode($value, true));
            }
        }
        return $r;
    }

    public function write($r) {
        // Write out non-annotation headers
        // then
        // Write out the response annotations as headers
        $this->writeOOB($r->oob());

        echo $r->encoded;
    }
}

