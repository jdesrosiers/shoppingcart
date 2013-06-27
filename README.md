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

Silex Extensions
================

* ContentNegotiationServiceProvider
* JmsSerializerServiceProvider
* ValidationServiceProvider

TODO List
=========

* Checkout the SecurityServiceProvider.  What are it's capabilities and limitations?  What other options are there?
* Checkout the TranslationServiceProvier.  What are it's capabilities and limitations?  What other options are there?
* Explore json hyper-schema as a way of satisfying the HATEOAS
* Create Silex middleware for CORS support
* Create Silex middleware for jsonp support
* Explore strategies for deploying and maintaining more than one version of an API
* Explore swagger/swagger-ui as a means of documenting the API
