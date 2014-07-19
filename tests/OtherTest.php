<?php
error_reporting(E_ALL);

include('setup.other-test.php');

class OtherTest extends \PHPUnit_Framework_TestCase {

    public function test_msgpack() {
        $client = OtherTestService::client();
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
}

