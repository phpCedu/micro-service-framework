<?php
error_reporting(E_ALL);

include('setup.other-test.php');

class OtherTest extends \PHPUnit_Framework_TestCase {

    public function test_msgpack() {
        $client = OtherTestService::client();
        try {
            $response = $client->reverse();
        } catch (\Exception $e) {
            $this->assertObjectHasAttribute('errors', $e);
            $this->assertInternalType('array', $e->errors);
            $this->assertCount(1, $e->errors);
            $this->assertEquals('Should be string: input', $e->errors[0]);
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
