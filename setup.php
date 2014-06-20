<?php
use MSF;

// IMPLEMENTATIONS

class MyServer extends Server {
    // Nothing custom yet
}

class MsgPackEncoder extends Encoder {
    public function encode($data) {
        return msgpack_pack($data);
    }
    public function decode($data) {
        return msgpack_unpack($data);
    }
}

class MyFilterDoesProfiling extends Filter {
    protected $started;
    protected $rpc;
    protected $profile;
    public function request(RequestResponse $request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
        $this->profile = $request->oob('profile');
        return $request;
    }
    public function response($response) {
        $this->profile['profile'] = array(
            'server.rpc' => $this->rpc,
            'start' => $this->started,
            'end' => microtime(true),
            'host' => gethostname()
        );
        $response->oob(
            'profile',
            $this->profile
        );
        return $response;
    }
}

class MyFilterConvertsRequest extends Filter {
    public function request(HTTPRequestResponse $request) {
        // Wrap it like an onion
        $req = new HTTPRequestResponse2($request);
        return $req;
    }
}
class HTTPRequestResponse2 extends HTTPRequestResponse {
}

class MyServiceHandler {
    
    public function childReverse($name) {
        $service = new MyService2();
        $client = $service->client();
        // 3 calls to make sure multiple OOB runtimes bubble up
        $name = $client->reverse($name);
        $name = $client->reverse($name);
        return $client->reverse($name);
    }

    // Need some type-checking on input params, right? or is that too tedious to do at this level?
    public function reverse($name) {
        return strrev($name);
    }
}

class MyService extends Service {
    public $endpoint = 'http://localhost:9999/index.php';
    public $transport;
    public $encoder;
    public static $clientClass = 'MyClient';

    public $definition = array(
        'reverse' => array(
            // return type
            'string',
            // param names
            array(
                'input'
            ),
            // associated param types
            array(
                'string'
            ),
        )
    );

    public function __construct() {
        $this->transport = new CurlTransport($this);
        // Always encoded in JSON, for now.
        $this->encoder = new JsonEncoder();

        // On the server side
        $this->handler = new MyServiceHandler();
    }
}
class MyService2 extends MyService {
    public $endpoint = 'http://localhost:9998/index.php';
}
class MyClient extends Client {
    public function preRequest($request) {
        $oob = array(
            'client.rpc' => $request->rpc,
            'started' => microtime(true),
            'host' => gethostname()
        );
        $request->oob('profile', $oob);
    }
}
