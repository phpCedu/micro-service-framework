<?php

include('Loader.php');
$loader = new Loader();
$loader->basePSR0 = __DIR__ . DIRECTORY_SEPARATOR . '../src/';
spl_autoload_register(array($loader, 'loadClass'));

// IMPLEMENTATIONS
class OtherTestService extends MSF\Service {
    public static $endpoint = 'http://localhost:9998/index.php';
    public static $encoderClass = '\MSF\Encoder\MsgPackEncoder';
    public static $clientClass = 'OtherTestClient';
    public static $serverClass = 'OtherTestServer';

    public static $definition = array(
        'reverse' => array(
            // return type
            'string',
            // param names
            array(
                'input',
            ),
            // associated param types
            array(
                'string',
            ),
        ),

        'badReturn' => array(
            'string'
        )
    );
}

class OtherTestClient extends \MSF\Client {
    protected $rpc;
    // Expose the response so we can view profiling data
    public $response;

    // These methods allow us to do profiling on client requests
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
        $this->response = $response;
    }
}


class OtherTestServer extends MSF\Server {
    // TODO - Fix this
    public static $transport = '\\MSF\\Transport\\PartialHTTPTransport';
}

// The actual service implementation is done inside a ServiceHandler
class OtherTestServiceHandler extends \MSF\ServiceHandler {
    public function reverse($name) {
        return strrev($name);
    }

    // Defined to return string, but we return false
    public function badReturn() {
        return false;
    }
}

