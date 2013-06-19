<?php

namespace JDesrosiers;

use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ContentNegotiation
{
    protected $request;
    protected $serializer;
    protected $serializationFormats;
    protected $deserializationFormats;

    public function __construct(Request $request, Serializer $serializer, $serializationFormats, $deserializationFormats)
    {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->serializationFormats = $serializationFormats;
        $this->deserializationFormats = $deserializationFormats;
    }

    public function createResponse($responseObject, $status = 200, array $headers = array())
    {
        $format = $this->request->getRequestFormat();

        // Just in case
        if (!in_array($format, $this->serializationFormats)) {
            throw new NotAcceptableHttpException();
        }

        $serializedContent = $this->serializer->serialize($responseObject, $format);

        // Set validation cache headers
        $response = new Response($serializedContent, $status, $headers);
        $response->setVary(array('Accept', 'Accept-Encoding', 'Accept-Charset'));
        $response->setEtag(md5($serializedContent));
        
        // TODO: For 201 responses ETag should be the ETag of the newly created entity
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.2.2

        return $response;
    }

    public function deserializeRequest(Request $request, $class)
    {
        $format = $request->getContentType();
        if (in_array($format, $this->deserializationFormats)) {
            return $this->serializer->deserialize($request->getContent(), $class, $format);
        } else {
            throw new UnsupportedMediaTypeHttpException();
        }
    }
}
