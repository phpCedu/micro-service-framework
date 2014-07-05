<?php

include('Loader.php');
$loader = new Loader();
$loader->basePSR0 = __DIR__ . DIRECTORY_SEPARATOR . '../src/';
spl_autoload_register(array($loader, 'loadClass'));

// IMPLEMENTATIONS

class MyServer extends MSF\Server {
    // Nothing custom yet
    public static $transport = '\\MSF\\Transport\\PartialHTTPTransport';
}

class MsgPackEncoder extends MSF\Encoder {
    public function encode($data) {
        return msgpack_pack($data);
    }
    public function decode($data) {
        return msgpack_unpack($data);
    }
}

class MyFilterDoesProfiling extends \MSF\Filter {
    protected $started;
    protected $rpc;
    protected $profile;
    public function request(\MSF\RequestResponse $request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
        $this->profile = $request->oob('profile');
        return $request;
    }
    public function response(\MSF\RequestResponse $response) {
        $response->oob(
            'profile',
            array(
                'server.rpc' => $this->rpc,
                'start' => $this->started,
                'end' => microtime(true),
                'host' => gethostname()
            )
        );
        return $response;
    }
}

class MyFilterConvertsRequest extends \MSF\Filter {
    public function request(\MSF\RequestResponse $request) {
        // Wrap it like an onion
        $req = new HTTPRequestResponse2($request);
        return $req;
    }
}
class HTTPRequestResponse2 extends \MSF\RequestResponse\HTTPRequestResponse {
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

class MyService extends MSF\Service {
    public static $endpoint = 'http://localhost:9999/index.php';
    public static $transport = '\\MSF\\Transport\\PartialHTTPTransport';
    public static $encoder = '\\MSF\\Encoder\\JsonEncoder';
    public static $clientClass = 'MyClient';
    public static $serverClass = 'MyServer';

    public static $definition = array(
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
}
class MyService2 extends MyService {
    public static $endpoint = 'http://localhost:9998/index.php';
}
class MyClient extends \MSF\Client {
    protected $rpc;
    public function preRequest($request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
    }
    public function postResponse($response) {
        $oob = array(
            'client.rpc' => $this->rpc,
            'started' => $this->started,
            'ended' => microtime(true),
            'host' => gethostname(),
            'profile' => $response->oob('profile')
        );
        // uhh, modifying a response is ugly
        $response->oob('profile', $oob);
    }
}
