<?php
namespace MSF\Transport;

// This class assumes headers have been parsed into $_SERVER and the HTTP body is in "php://input"
// It writes out similarly ... echoing the body and using header()
class PartialHTTPTransport extends \MSF\Transport\HTTPTransport {
    public function readRequest() {
        $r = new \MSF\Request\HTTPRequest();
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

        $r->response = new \MSF\Resposne\HTTPResponse();
        return $r;
    }

    public function writeResponse(\MSF\Response\HTTPResponse $r) {
        // Write out non-annotation headers
        // then
        // Write out the response annotations as headers
        $this->writeOOB($r->oob());

        echo $r->encoded;
        return strlen($r->encoded);
    }

    protected function writeOOB($oob) {
        foreach ($oob as $key => $value) {
            header('HTTP_Z_' . strtoupper($key) . ':' . json_encode($value));
        }
    }
}

