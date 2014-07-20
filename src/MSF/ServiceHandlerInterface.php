<?php
namespace MSF;

interface ServiceHandlerInterface {
    // This is so our service handler can get access at our service instance ...
    // Useful if the handler needs to make client calls to our service
    // If the service handler needs to do so, it should store the context somewhere for future use
    public function serviceContext(\MSF\Service $service);
}
