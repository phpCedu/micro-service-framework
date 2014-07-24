<?php

include('Loader.php');
$loader = new Loader();
$loader->basePSR0 = __DIR__ . DIRECTORY_SEPARATOR . '../src/';
spl_autoload_register(array($loader, 'loadClass'));

// IMPLEMENTATIONS
class ClientTestService extends MSF\Service {
    public static $endpoint = 'http://localhost:9999/index.php';

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

    public function client() {
        // think client and server need separate transports
        return new ClientTestClient(
            $this,
            new \MSF\Transport\CurlTransport(
                static::$endpoint,
                new \MSF\Encoder\JsonEncoder()
            )
        );
    }
    public function server() {
        return new ClientTestServer(
            $this,
            new ClientTestServiceHandler($this),
            new \MSF\Transport\PartialHTTPTransport(
                static::$endpoint,
                new \MSF\Encoder\JsonEncoder()
            )
        );
    }
}

class ClientTestClient extends \MSF\Client {
    public function setup() {
        $this->filters[] = new ClientProfilingFilter();
    }
}


class ClientTestServer extends MSF\Server {
    public function setup() {
        $this->filters[] = new ServerRequestThrottlingFilter();
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
    public function request(\MSF\Request $request) {
        $this->rpc = $request->rpc;
        $this->started = microtime(true);
        $this->profile = $request->oob('profile');
        return $request;
    }
    public function response(\MSF\Response $response) {
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
class ServerRequestThrottlingFilter implements \MSF\FilterInterface {
    public function request(\MSF\Request $request) {
        // Connect to redis
        require '/home/alan/projects/periodic/vendor/predis-0.8.6/lib/Predis/Autoloader.php';
        Predis\Autoloader::register();

        $ip = $_SERVER['REMOTE_ADDR'];
        $ts = date('YmdHi');
        $client = new Predis\Client();
        $val = $client->incr($ip . ':' . $ts);
        if ($val > 2) {
            $request->response->addError('Too fast');
            return $request->response;
        }
        return $request;
    }
    public function response(\MSF\Response $response) {
        return $response;
    }
}


// API VERSION 2
class ClientTestService2 extends ClientTestService {
    public static $endpoint = 'http://localhost:9999/index.php';

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
