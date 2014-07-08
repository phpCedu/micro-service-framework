<?php
error_reporting(E_ALL);

include('setup.php');

class ClientTest extends \PHPUnit_Framework_TestCase {
    protected $client;

    public function setUp() {
        // Service knows where the endpoint is, which calls are available, which protocol, and which transport
        $this->client = MyService::client();
    }

    public function test_simple_with_profiling() {
        try {
            $response = $this->client->reverse('hey', 5);
            $this->assertEquals('yeh', $response);

            // Our custome MyClient class stores profiling data in the response, and makes the response publicly available
            $this->assertInternalType('array', $this->client->response->oob('profile'));
            // Need to test profile format too

        } catch (\Exception $e) {
            echo 'One: ' . implode("\r\n", $e->errors) . "\r\n";
        }
    }

    public function test_definition_enforcement() {

        // Validate param type
        try {
            // Client calls with bad params throw exceptions here
            $response = $this->client->reverse(false, 'should not be string');
        } catch (\Exception $e) {
            $this->assertObjectHasAttribute('errors', $e);
            // Make sure there are 2 errors about param types
            $this->assertCount(2, $e->errors);
            // TODO - Make sure the errors are what we'd expect
        }

        // Validate return type
        try {
            // Bad return values on server side turn into error responses
            $response = $this->client->badReturn();
            $this->assertObjetHasAttribute('errors', $response);
            // Make sure there's 1 error about return type
            $this->assertCount(1, $response->errors);
        } catch (\Exception $e) {
            // Wait a minute, the client promotes response errors to exceptions? is that right?
            // That means we never get the response object directly ... hmm, decisions
            // TODO - should we throw exceptions, or return response objects with error messages?
            echo implode("\r\n", $e->errors) . "\r\n";
        }
    }
}


// More complex data types
/*
try {
    $data = array(
        'name' => 'Name',
        'ids' => array(1,5,34,77)
    );
    $out = $client->yessir($data);
    var_dump($out);
} catch (\Exception $e) {
    var_dump($e);
}
*/
