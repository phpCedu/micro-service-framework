<?php
namespace MSF;

// Each filter sees the request coming in, and the response going out (in reverse order through the filter stack)
interface FilterInterface {
    // Inside request(), annotate $request->response with error messages to signal an error
    public function request(\MSF\Request $request);
    public function response(\MSF\Response $response);
}

