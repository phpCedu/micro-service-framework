<?php
namespace MSF;

// Each filter sees the request coming in, and the response going out (in reverse order through the filter stack)
// BUT FILTERS SHOULDN'T MODIFY THE REQUEST OR RESPONSE BODY
interface FilterInterface {
    // Should return some instance of BaseResponse
    public function request(\MSF\RequestResponse $request);
    public function response(\MSF\RequestResponse $response);
}

