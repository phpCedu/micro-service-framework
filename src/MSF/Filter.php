<?php
namespace MSF;

// Each filter sees the request coming in, and the response going out (in reverse order through the filter stack)
// BUT FILTERS SHOULDN'T BE ALLOWED TO MODIFY THE REQUEST OR RESPONSE BODY
class Filter {
    // Should return some instance of BaseResponse
    public function request(RequestResponse $request) {
        return $request;
    }
    public function response(RequestResponse $response) {
        return $response;
    }
}

