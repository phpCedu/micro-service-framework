Micro Services Framework
====

This framework is similar to Apache Thrift, but tries to be simpler. Not specifically for REST-based RPC services ... you can create your own transports (HTTP, TCP) like in Thrift. RPC calls can be encoded in JSON or MsgPack

Requirements
====

PHP 5.3.0 or greater

Usage
====

Extend the following classes:

* \MSF\Service - Configure the following:
  * Static interface definition
  * Method to return a client instance
  * Method to return a server instance
* \MSF\Server
* \MSF\Client
* \MSF\ServiceHandler

Call `YourService::getInstance()->server()` to get an instance of your server. Then `$server->run()`.
Call `YourService::getInstance()->client()` to get an instance of your client. Then `$client->your_rpc_call($param1, $param2)`.

See `tests/setup.client-test.php` for a thorough example

TODO
====

* Do simple type validation on the fields that are present, according to what's expected in the service definition. This requires the programmer to keep old/deprecated fields around until he/she absolutely doesn't want to support old clients anymore. RPC methods will receive a stdClass instance with new and/or old fields, whatever was passed, that's also present in the service definition.
* ServiceValidator - same structure as ServiceHandler ... you implement validation. SimpleServiceValidator would do type-checking from service definition
* Set up for installation via composer
* Give more thought to schema evolution ... test when Server has a newer Service definition than the client
* Probably should stub out a Transport, and test Server and Filter interaction directly: make sure Filters can throw exceptions

THOUGHTS
====

How do we help the schema evolve? Would type-checking the passed params be sufficient, allowing the others to be null? Maybe it's up to newer service backends to fail when they do really need all the newest params. But that would get quite tedious, to have to add manual validation for all required params to the service handler methods.
