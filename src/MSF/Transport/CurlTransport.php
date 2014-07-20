<?php
namespace MSF\Transport;

class CurlTransport extends \MSF\Transport\HTTPTransport {
    protected $response;

    public function readResponse() {
        return $this->response->readResponse();
    }
    public function writeRequest(\MSF\Request $request) {
        // Somehow need to get the service endpoint
        $ch = curl_init($this->endpoint);
        if(!$ch) {
            $e = curl_error($ch);
            throw new \Exception('Request failed: ' . $e);
        }
        curl_setopt($ch, CURLOPT_HEADER, 1); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->encoded);
        $headers = array();
        foreach ($request->oob() as $key => $value) {
            $headers[] = 'Z_' . $key . ':' . json_encode($value);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch); //execute post and get results

        if($response === false) {
            $e = curl_error($ch);
            throw new \Exception('Request failed: ' . $e);
        } else {
            // Since cURL returns the response right-away, save it for later
            // ... will be returned by read()
            // Leverage the HTTPTransport to parse the HTTP response
            $this->response = new \MSF\Transport\HTTPTransport($this->endpoint);
            $this->response->data($response);
        }
        return strlen($request->encoded);
    }
}

