RESTful shoppingcart web service
============

This is a toy project I'm working on.  It has the following goals.

* Learn the Silex framework https://github.com/fabpot/Silex
* Make a web service that even Roy Fielding would consider RESTful
* End up with something that can be used as a starting point for quickly producing RESTful services in the future

Features
========

Typical GET, POST, PUT, DELETE operations
POST 303 pattern
ContentNegotiation module
CORS support
Swagger API definition using annotations

Silex Extensions
================

ContentNegotiationServiceProvider
---------------------------------
This serivice provides HTTP content negotiation tools.  It deals with the content negotiation headers and responses so
you don't need to think about it.  Your code just needs to use JMS Serializer annotated classes and everything else is
taken care of automatically.

* `conneg.serializer` A JMS Serializer instance
* `conneg.serializationFormats` Defaults to XML, JSON, and YML
* `conneg.deserializationFormats` Defaults to XML and JSON
* `conneg.defaultContentType` Defaults to JSON
* `conneg` Provides a ContentNegotiation object with two methods
 * `createResponse` This works just like instatiating a new Respose, but takes a JMS Serializer annotated object instead of a string and serializes it to the format the user requested.
 * `deserializeRequest` Pass it a class name and it will deserialize the request entity and give you back an instance of that class.

CorsServiceProvider
-------------------
The CorsServiceProvider provides a service "cors" that can be added as after middleware to the Application, a
ControllerCollection, or a Route.  It also goes through all routes and generates all necessary OPTIONS methods.  You
can set values for all of the CORS response headers

 * `cors.allowOrigin`
 * `cors.allowMethods`
 * `cors.maxAge`
 * `cors.allowCredentials`
 * `cors.exposeHeaders`

JmsSerializerServiceProvider
----------------------------

SwaggerServiceProvider
----------------------
The swagger service adds a route that will return the swagger definition in a way that swagger-ui understands.

 * `swagger.servicePath` Tell swagger where to look for annotations
 * `swagger.apiDocPath` Defaults to /api-docs.json

ValidationServiceProvider
-------------------------

TODO List
=========

* Checkout the SecurityServiceProvider.  What are it's capabilities and limitations?  What other options are there?
* Checkout the TranslationServiceProvier.  What are it's capabilities and limitations?  What other options are there?
* Explore json hyper-schema as a way of satisfying the HATEOAS requirement of REST
* Create Silex middleware for jsonp support
* Explore strategies for deploying and maintaining more than one version of an API
