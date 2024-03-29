<?php
error_reporting(E_ALL);

include('setup.client-test.php');

class ClientTest extends \PHPUnit_Framework_TestCase {

    public function test_simple_with_profiling() {
        $client = ClientTestService::getInstance()->client();
        try {
            $response = $client->reverse();
        } catch (\Exception $e) {
            $this->assertObjectHasAttribute('errors', $e);
            $this->assertInternalType('array', $e->errors);
            $this->assertCount(1, $e->errors);
            $this->assertEquals('"input" should be one of: string', $e->errors[0]);
        }
        $this->assertNotNull($e);
        try {
            $response = $client->reverse('hey');
        } catch (\Exception $e) {
            // This shouldn't happen
            echo 'One: ' . implode("\r\n", $e->errors) . "\r\n";
        }
        $this->assertEquals('yeh', $response);

        // Our custome MyClient class stores profiling data in the response, and makes the response publicly available
        $profile = $client->response->oob('profile');
        $this->assertInternalType('array', $profile);
        // Need to test profile format too
        $this->assertCount(5, $profile);
    }

    public function test_newer_endpoint() {
        $client = ClientTestService2::getInstance()->client();
        try {
            $response = $client->reverse('hey', 5);
        } catch (\Exception $e) {
            // This shouldn't happen
            echo $e->getMessage();
            //var_dump($e->getTrace());exit;
            //echo 'One: ' . implode("\r\n", $e->errors) . "\r\n";
        }
        $this->assertEquals('yeh', $response);

        // Our custome MyClient class stores profiling data in the response, and makes the response publicly available
        $profile = $client->response->oob('profile');
        $this->assertInternalType('array', $profile);
        // Need to test profile format too
        $this->assertCount(5, $profile);
    }


    public function test_definition_enforcement() {
        $client = ClientTestService2::getInstance()->client();

        // Validate param type
        try {
            // Client calls with bad params throw exceptions here
            $response = $client->reverse(false, 'should not be string');
        } catch (\Exception $e) {
            $this->assertObjectHasAttribute('errors', $e);
            // Make sure there are 2 errors about param types
            $this->assertCount(2, $e->errors);
            $this->assertEquals('"input" should be one of: string', $e->errors[0]);
            $this->assertEquals('"times" should be one of: int32, null', $e->errors[1]);
        }
        // Need to make sure response is null, and exception was thrown
    }
}
