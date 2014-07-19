<?php

include('Loader.php');
$loader = new Loader();
$loader->basePSR0 = __DIR__ . DIRECTORY_SEPARATOR . '../src/';
spl_autoload_register(array($loader, 'loadClass'));

// IMPLEMENTATIONS
class ClientTestService extends MSF\Service {
    public static $endpoint = 'http://localhost:9999/index.php';
    public static $encoder = '\\MSF\\Encoder\\JsonEncoder';
    public static $clientClass = 'ClientTestClient';
    public static $serverClass = 'ClientTestServer';

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

class ClientTestClient extends \MSF\Client {
    protected $rpc;
    // Expose the response so we can view profiling data
    public $response;

    public function __construct($serviceClass, $transport, $encoder) {
        parent::__construct($serviceClass, $transport, $encoder);
        $this->filters[] = new ClientProfilingFilter();
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


class ClientTestServer extends MSF\Server {
    // TODO - Fix this
    public static $transport = '\\MSF\\Transport\\PartialHTTPTransport';

    public function __construct($serviceClass, $handler) {
        parent::__construct($serviceClass, $handler);
        // Set up default filters
        $this->filters[] = new ServerProfilingFilter();
    }
}

// The actual service implementation is done inside a ServiceHandler
class ClientTestServiceHandler extends \MSF\ServiceHandler {
    public function reverse($name) {
        return strrev($name);
    }

    // Defined to return string, but we return false
    public function badReturn() {
        return false;
    }
}


// Core filter functionality
class ProfilingFilter implements \MSF\FilterInterface {
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
class ClientProfilingFilter extends ProfilingFilter {
    protected $prefix = 'client.';
}
class ServerProfilingFilter extends ProfilingFilter {
    protected $prefix = 'server.';
}


// API VERSION 2
class ClientTestService2 extends MSF\Service {
    public static $endpoint = 'http://localhost:9999/index.php';
    public static $encoder = '\\MSF\\Encoder\\JsonEncoder';
    public static $clientClass = 'ClientTestClient';
    public static $serverClass = 'ClientTestServer';

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
                'null-int32'
            ),
        ),
        'badReturn' => array(
            'string'
        )
    );
}

// The actual service implementation is done inside a ServiceHandler
class ClientTestServiceHandler2 extends \MSF\ServiceHandler {
    public function reverse($name, $times) {
        if (is_null($times)) {
            $times = 1;
        }
        for ($i = 0; $i < $times; $i++) {
            $name = strrev($name);
        }
        return $name;
    }

    // Defined to return string, but we return false
    public function badReturn() {
        return false;
    }
}
