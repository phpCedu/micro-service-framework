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
