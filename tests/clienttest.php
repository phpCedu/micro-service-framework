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
        } catch (\Exception $e) {
            // This shouldn't happen
            //echo 'One: ' . implode("\r\n", $e->errors) . "\r\n";
        }
        $this->assertEquals('yeh', $response);

        // Our custome MyClient class stores profiling data in the response, and makes the response publicly available
        $profile = $this->client->response->oob('profile');
        $this->assertInternalType('array', $profile);
        // Need to test profile format too
        $this->assertCount(5, $profile);
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
            $this->assertEquals('Should be string: input', $e->errors[0]);
            $this->assertEquals('Should be int32: times', $e->errors[1]);
        }
        // Need to make sure response is null, and exception was thrown

        // Validate return type
        try {
            // Bad return values on server side turn into error responses
            $response = $this->client->badReturn();
        } catch (\Exception $e) {
            $this->assertObjectHasAttribute('errors', $e);
            // Make sure there's 1 error about return type
            $this->assertCount(1, $e->errors);
            $this->assertEquals('RPC call was expected to return a string', $e->errors[0]);

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
