<?php
namespace MSF\Transport;

class CurlTransport extends \MSF\Transport\HTTPTransport {
    protected $response;

    public function read() {
        // read() makes no sense for Curl ... once we write, we automatially read too
        return $this->response->read();
    }
    public function write($r) {
        // Somehow need to get the service endpoint
        $url = $this->service->endpoint;
        $ch = curl_init($url);
        if(!$ch) {
            die('Curl Error');
        }
        curl_setopt($ch, CURLOPT_HEADER, 1); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $r->encoded);
        $headers = array();
        foreach ($r->oob() as $key => $value) {
            $headers[] = 'Z_' . $key . ':' . json_encode($value);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch); //execute post and get results

        if($response === false) {
            $e = curl_error($ch);
            throw new \Exception('Request failed: ' . $e);
        } else {
            // Leverage the HTTPTransport to parse the HTTP response
            $t = new \MSF\Transport\HTTPTransport($this->service);
            $t->data($response);
            $this->response = $t;
        }
    }
}
