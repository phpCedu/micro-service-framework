Micro Services Framework
====

This framework is inspired by Apache Thrift, but tries to be simpler. It explores the means of providing schema evolution within a light micro-service framework. Thrift accomplishes this by using protocol buffers, but I was curious whether the benefits of protocol buffers could be achieved in different ways. Being a Tolerant Reader (bob martin) might be the first step toward support of schema evolution, so that is the approach I'm taking for now.

While it comes with support for HTTP-based RPC services, it's not limited to that. As with Thrift, you can create your own transport to make RPC calls over HTTP, TCP, and so on. The RPC data can also be encoded in JSON, MsgPack, or an encoding added by you.


Internals
====

Our RPC data structure looks like this JSON representation:

`{
    "rpc": "name_of_rpc_method",
    "args": {
        "arg1": "arg1value",
        "arg2": "arg2value"
    }
}`

The above is constructed by the Request object on the client side, passed through an encoder, and then passed to the transport.

Scenario 1 - Client sending fields that are no longer supported
    The server-side service handler method may not be defined to accept old fields, but they are still accepted. The method can access them via `$this->args->old_field_name`, etc. They may not be passed into the method, but they're still available.

Scenario 2 - Client failing to send values for new fields
    To handle this scenario, it's up to you to configure the proper validation on your service. If a field is absolutely necessary, then by all means return a validation error. But if you opt to still support old clients, then be forgiving and preserve the older functionality the client is expecting.

Goals of schema evolution support:

* Clients that aren't aware of the new schema can still use the service, but only if it makes sense to allow them to do so. One instance where it might not: if old request parameters were encrypted with an encryption scheme that is known to be compromised, you should likely force all clients to migrate to the new schema.
* Are there others?

How to Evolve a Schema
    Add new fields to your service definition, with types if using the SimpleServiceValidator. If you need to drastically change the types allowed on an existing field, it's probably best to simply add a new field and deprecate the old one. That way a human looking at the field names doesn't get confused, and quite frankly, this framework doesn't keep track of field versions so there's no way to validate using the older method an old client might be expecting.


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

* RPC method definition should have deprecated fields section as well, so we know how to validate them if they arrive.
* Do simple type validation on the fields that are present, according to what's expected in the service definition. This requires the programmer to keep old/deprecated fields around until he/she absolutely doesn't want to support old clients anymore. RPC methods will receive a stdClass instance with new and/or old fields, whatever was passed, that's also present in the service definition.
* Set up for installation via composer
* Give more thought to schema evolution ... test when Server has a newer Service definition than the client
* Probably should stub out a Transport, and test Server and Filter interaction directly: make sure Filters can throw exceptions

THOUGHTS
====

How do we help the schema evolve? Would type-checking the passed params be sufficient, allowing the others to be null? Maybe it's up to newer service backends to fail when they do really need all the newest params. But that would get quite tedious, to have to add manual validation for all required params to the service handler methods.

Hmm, an old client might not expect a new authentication filter. Framework and schema evolution doesn't address this.
