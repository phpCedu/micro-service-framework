<?php
namespace MSF\Transport;

// This should probably be renamed to PHPTransport
// This class assumes headers have been parsed into $_SERVER and the HTTP body is in "php://input"
// It writes out similarly ... echoing the body and using header()
class PartialHTTPTransport extends \MSF\Transport\HTTPTransport {
    public function readRequest() {
        $request = new \MSF\Request\HTTPRequest();
        $request->encoded = file_get_contents('php://input');
        foreach ($_SERVER as $key => $value) {
            if (strncasecmp($key, 'HTTP_Z_', 7) == 0) {
                $key = strtolower(substr($key, 7));
                $request->oob($key, json_decode($value, true));
            }
        }
        $request->decodeUsing($this->encoder);
        // Make the response now so Filters have it handy, and can annotate with errors if necessary
        $request->response = new \MSF\Response\HTTPResponse();
        return $request;
    }

    public function writeResponse(\MSF\Response $response) {
        foreach ($response->oob() as $key => $value) {
            header('HTTP_Z_' . strtoupper($key) . ':' . json_encode($value));
        }
        $response->encodeUsing($this->encoder);
        echo $response->encoded;
        return strlen($response->encoded);
    }
}

