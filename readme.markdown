Requirements
====

PHP 5.3.0 or greater

Usage
====

Extend the following classes:

* Service - Configure the following:
  * Class name of your client
  * Class name of your server
  * Interface definition
  * Class name of transport to use
  * Class name of encoder to use
* Server
* Client

Call `MyService::server()` to get an instance of your server.
Call `MyService::client()` to get an instance of your client.

TODO
====

* Set up for installation via composer
* Implement same sort of filter loops in Client as in Server ... allow filters to throw errors, pop off filter stack, essentially
* Test when Server has a newer Service definition than the client

THOUGHTS
====

How do we help the schema evolve? Would type-checking the passed params be sufficient, allowing the others to be null?

Maybe it's up to newer service backends to fail when they do really need all the newest params. But that would get quite tedious, to have to add manual validation for all required params to the service handler methods.

What about adding additional types like null-string, null-int32? Would allow more control over preventing nulls.
