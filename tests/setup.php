<?php

include('Loader.php');
$loader = new Loader();
$loader->basePSR0 = __DIR__ . DIRECTORY_SEPARATOR . '../src/';
spl_autoload_register(array($loader, 'loadClass'));

// IMPLEMENTATIONS

class MyServer extends MSF\Server {
    // TODO - Fix this
    public static $transport = '\\MSF\\Transport\\PartialHTTPTransport';

    public function __construct($serviceClass, $handler) {
        parent::__construct($serviceClass, $handler);
        // Set up default filters
        $this->filters[] = new MyServerProfilingFilter();
        //$this->filters[] = new MyFilterConvertsRequest();
    }
}

// Server-side filters
class MyProfilingFilter implements \MSF\FilterInterface {
    protected $started;
    protected $rpc;
    protected $profile;
    protected $prefix = '';
    public function request(\MSF\RequestResponse $request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
        $this->profile = $request->oob('profile');
        return $request;
    }
    public function response(\MSF\RequestResponse $response) {
        $oob = array(
            'client.rpc' => $this->rpc,
            'started' => $this->started,
            'ended' => microtime(true),
            'host' => gethostname()
        );
        $profile = $response->oob('profile');
        if ($profile) {
            $oob['profile'] = $profile;
        }
        // uhh, modifying a response is ugly
        $response->oob('profile', $oob);
        return $response;
    }
}
class MyClientProfilingFilter extends MyProfilingFilter {
    protected $prefix = 'client.';
}
class MyServerProfilingFilter extends MyProfilingFilter {
    protected $prefix = 'server.';
}
// Bah
/*
class MyFilterConvertsRequest extends \MSF\Filter {
    public function request(\MSF\RequestResponse $request) {
        // Wrap it like an onion
        $req = new HTTPRequestResponse2($request);
        return $req;
    }
}
class HTTPRequestResponse2 extends \MSF\RequestResponse\HTTPRequestResponse {
}
*/

// The actual service implementation is done inside a ServiceHandler
class MyServiceHandler extends \MSF\ServiceHandler {
    /*
    public function childReverse($name) {
        $service = new MyService2();
        $client = $service->client();
        // 3 calls to make sure multiple OOB runtimes bubble up
        $name = $client->reverse($name);
        $name = $client->reverse($name);
        return $client->reverse($name);
    }
    */

    // Need some type-checking on input params, right? or is that too tedious to do at this level?
    public function reverse($name, $times = 1) {
        for ($i = 0; $i < $times; $i++) {
            $name = strrev($name);
        }
        return $name;
    }

    // Defined to return string
    public function badReturn() {
        return false;
    }
}

class MyService extends MSF\Service {
    public static $endpoint = 'http://localhost:9999/index.php';
    public static $encoder = '\\MSF\\Encoder\\JsonEncoder';
    public static $clientClass = 'MyClient';
    public static $serverClass = 'MyServer';

    public static $definition = array(
        'reverse' => array(
            // return type
            'string',
            // param names
            array(
                'input',
                'times'
            ),
            // associated param types
            array(
                'string',
                'int32'
            ),
        ),

        'yessir' => array(
            'array',
            // param names
            array(
                'data'
            ),
            array('array')
        ),

        'badReturn' => array(
            'string'
        )
    );
}
class MyService2 extends MyService {
    public static $endpoint = 'http://localhost:9998/index.php';
}

class MyClient extends \MSF\Client {
    protected $rpc;
    // Expose the response so we can view profiling data
    public $response;

    public function __construct($serviceClass, $transport, $encoder) {
        parent::__construct($serviceClass, $transport, $encoder);
        $this->filters[] = new MyClientProfilingFilter();
    }

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
