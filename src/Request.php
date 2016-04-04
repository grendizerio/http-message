<?php

namespace Grendizer\HttpMessage;

/**
 * Request
 *
 * This class represents an HTTP request. It manages
 * the request method, URI, headers, cookies, and body
 * according to the PRS-7 standard.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/MessageInterface.php
 * @link https://github.com/php-fig/http-message/blob/master/src/RequestInterface.php
 * @link https://github.com/php-fig/http-message/blob/master/src/ServerRequestInterface.php
 */
class Request extends Message implements ServerRequestInterface
{
    /**
     * The request method
     *
     * @var string
     */
    protected $method;

    /**
     * The original request method (ignoring override)
     *
     * @var string
     */
    protected $originalMethod;

    /**
     * The request URI object
     *
     * @var RequestUri
     */
    protected $uri;

    /**
     * The request URI target (path + query string)
     *
     * @var string
     */
    protected $requestTarget;

    /**
     * The request query string params
     *
     * @var Bag
     */
    protected $queryParams;

    /**
     * The request cookies
     *
     * @var Bag
     */
    protected $cookieParams;

    /**
     * The server environment variables at the time the request was created.
     *
     * @var ServerBag
     */
    protected $serverParams;

    /**
     * @var Session
     */
    protected $sessionParams;

    /**
     * The request attributes (route segment names and values)
     *
     * @var Bag
     */
    protected $attributes;

    /**
     * The request body parsed (if possible) into a PHP array or object
     *
     * @var null|array|object
     */
    protected $bodyParsed;

    /**
     * List of request body parsers (e.g., url-encoded, JSON, XML, multipart)
     *
     * @var callable[]
     */
    protected $bodyParsers = array();

    /**
     * List of uploaded files
     *
     * @var UploadedFileBag
     */
    protected $uploadedFiles;

    /**
     * Valid request methods
     *
     * @var string[]
     */
    protected $validMethods = array(
        'CONNECT' => 1,
        'DELETE' => 1,
        'GET' => 1,
        'HEAD' => 1,
        'OPTIONS' => 1,
        'PATCH' => 1,
        'POST' => 1,
        'PUT' => 1,
        'TRACE' => 1,
    );

    /**
     * Create new HTTP request with data extracted from the application
     * Environment object
     *
     * @return self
     */
    public static function createFromGlobal()
    {
        // With the php's bug #66606, the php's built-in web server
        // stores the Content-Type and Content-Length header values in
        // HTTP_CONTENT_TYPE and HTTP_CONTENT_LENGTH fields.
        $server = $_SERVER;
        if ('cli-server' === php_sapi_name()) {
            if (array_key_exists('HTTP_CONTENT_LENGTH', $_SERVER)) {
                $server['CONTENT_LENGTH'] = $_SERVER['HTTP_CONTENT_LENGTH'];
            }
            if (array_key_exists('HTTP_CONTENT_TYPE', $_SERVER)) {
                $server['CONTENT_TYPE'] = $_SERVER['HTTP_CONTENT_TYPE'];
            }
        }

        $request = new static($_GET, $_COOKIE, $server, $_FILES, array(), null);

        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $mediaTypes = array('application/x-www-form-urlencoded', 'multipart/form-data');

        if ($method === 'POST' && in_array($request->getMediaType(), $mediaTypes)) {
            // parsed body must be $_POST
            $request = $request->withParsedBody($_POST);
        }
        
        return $request;
    }

    /**
     * Create new HTTP request.
     *
     * Adds a host header when none was provided and a host is defined in uri.
     *
     * @param array            $queryParams   The request cookies collection
     * @param array            $cookieParams  The request cookies collection
     * @param array            $serverParams  The server environment variables
     * @param array            $uploadedFiles The request uploadedFiles collection
     * @param array            $attributes    The server environment variables
     * @param array|StreamInterface $body     The request body object
     */
    public function __construct(
        array $queryParams = array(),
        array $cookieParams = array(),
        array $serverParams = array(),
        array $uploadedFiles = array(),
        array $attributes = array(),
        $body = array()
    ) {
        $this->queryParams = new Bag($queryParams);
        $this->cookieParams = new Bag($cookieParams);
        $this->serverParams = new ServerBag($serverParams);
        $this->uploadedFiles = new UploadedFileBag($uploadedFiles);
        $this->attributes = new Bag($attributes);

        if (is_array($body)) {
            $this->bodyParsed = new Bag($body);
        } elseif ($body instanceof StreamInterface) {
            $this->body = $body;
        }

        $server = $this->serverParams;

        $this->headerParams = new HeaderBag($server->getHeaders());
        $this->originalMethod = $this->filterMethod($server->get('REQUEST_METHOD'));
        $this->uri = new RequestUri($this);

        if ($server->has('SERVER_PROTOCOL')) {
            $this->protocolVersion = str_replace('HTTP/', '', $server->get('SERVER_PROTOCOL'));
        }

        if (!$this->headerParams->has('Host') || $this->uri->getHost() !== '') {
            $this->headerParams->set('Host', $this->uri->getHost());
        }

        $this->registerMediaTypeParser('application/json', function ($input) {
            return json_decode($input, true);
        });

        $this->registerMediaTypeParser('application/xml', function ($input) {
            $backup = libxml_disable_entity_loader(true);
            $result = simplexml_load_string($input);
            libxml_disable_entity_loader($backup);
            return $result;
        });

        $this->registerMediaTypeParser('text/xml', function ($input) {
            $backup = libxml_disable_entity_loader(true);
            $result = simplexml_load_string($input);
            libxml_disable_entity_loader($backup);
            return $result;
        });

        $this->registerMediaTypeParser('application/x-www-form-urlencoded', function ($input) {
            parse_str($input, $data);
            return $data;
        });
    }

    /**
     * This method is applied to the cloned object
     * after PHP performs an initial shallow-copy. This
     * method completes a deep-copy by creating new objects
     * for the cloned object's internal reference pointers.
     */
    public function __clone()
    {
        $this->headerParams = clone $this->headerParams;
        $this->attributes = clone $this->attributes;
        $this->body = clone $this->body;
    }

    /**
     * Returns the request as a string.
     *
     * @return string The request
     */
    public function __toString()
    {
        try {
            $content = $this->body->getContents();
        } catch (\LogicException $e) {
            return trigger_error($e, E_USER_ERROR);
        }

        return
            sprintf('%s %s %s', $this->getMethod(), $this->getRequestTarget(), $this->serverParams->get('SERVER_PROTOCOL'))."\r\n".
            $this->headerParams."\r\n".
            $content;
    }

    /*******************************************************************************
     * Session
     ******************************************************************************/

    /**
     * Gets the Session.
     *
     * @return SessionInterface|null The session
     */
    public function getSession()
    {
        return $this->sessionParams;
    }

    /**
     * Sets the Session.
     *
     * @param SessionInterface $session The Session
     */
    public function setSession(SessionInterface $session)
    {
        $this->sessionParams = $session;
    }

    /**
     * Sets the Session by native
     */
    public function setNativeSession()
    {
        $this->sessionParams = new Session();
    }

    /**
     * Whether the request contains a Session object.
     *
     * This method does not give any information about the state of the session object,
     * like whether the session is started or not. It is just a way to check if this Request
     * is associated with a Session instance.
     *
     * @return bool true when the Request contains a Session object, false otherwise
     */
    public function hasSession()
    {
        return null !== $this->sessionParams;
    }

    /*******************************************************************************
     * Method
     ******************************************************************************/

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        if ($this->method === null) {
            $this->method = $this->originalMethod;
            $customMethod = $this->getHeaderLine('X-Http-Method-Override');

            if ($customMethod) {
                $this->method = $this->filterMethod($customMethod);
            } elseif ($this->originalMethod === 'POST') {
                $body = $this->getParsedBody();

                if (is_object($body) && property_exists($body, '_METHOD')) {
                    $this->method = $this->filterMethod((string)$body->_METHOD);
                } elseif (is_array($body) && isset($body['_METHOD'])) {
                    $this->method = $this->filterMethod((string)$body['_METHOD']);
                }

                if ($this->getBody()->eof()) {
                    $this->getBody()->rewind();
                }
            }
        }

        return $this->method;
    }

    /**
     * Get the original HTTP method (ignore override).
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string
     */
    public function getOriginalMethod()
    {
        return $this->originalMethod;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-sensitive method.
     * @return self
     * @throws \InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method)
    {
        $method = $this->filterMethod($method);
        $clone = clone $this;
        $clone->originalMethod = $method;
        $clone->method = $method;

        return $clone;
    }

    /**
     * Validate the HTTP method
     *
     * @param  null|string $method
     * @return null|string
     * @throws \InvalidArgumentException on invalid HTTP method.
     */
    protected function filterMethod($method)
    {
        if ($method === null) {
            return $method;
        }

        if (!is_string($method)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported HTTP method; must be a string, received %s',
                (is_object($method) ? get_class($method) : gettype($method))
            ));
        }

        $method = strtoupper($method);
        if (!isset($this->validMethods[$method])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported HTTP method "%s" provided',
                $method
            ));
        }

        return $method;
    }

    /**
     * Does this request use a given method?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param  string $method HTTP method
     * @return bool
     */
    public function isMethod($method)
    {
        return $this->getMethod() === $method;
    }

    /**
     * Is this an XMLHttpRequest request?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isXMLHttpRequest()
    {
        return $this->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /*******************************************************************************
     * URI
     ******************************************************************************/

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        if ($this->requestTarget) {
            return $this->requestTarget;
        }

        if ($this->uri === null) {
            return '/';
        }

        $basePath = $this->uri->getBasePath();
        $path = $this->uri->getPath();
        $path = $basePath . '/' . ltrim($path, '/');

        $query = $this->uri->getQuery();
        if ($query) {
            $path .= '?' . $query;
        }
        $this->requestTarget = $path;

        return $this->requestTarget;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-2.7 (for the various
     *     request-target forms allowed in request messages)
     * @param mixed $requestTarget
     * @return self
     * @throws \InvalidArgumentException if the request target is invalid
     */
    public function withRequestTarget($requestTarget)
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException(
                'Invalid request target provided; must be a string and cannot contain whitespace'
            );
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return self
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost) {
            if ($uri->getHost() !== '') {
                $clone->headerParams->set('Host', $uri->getHost());
            }
        } else {
            if ($this->uri->getHost() !== '' && (!$this->hasHeader('Host') || $this->getHeader('Host') === null)) {
                $clone->headerParams->set('Host', $uri->getHost());
            }
        }

        return $clone;
    }

    /**
     * Get request content type.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string|null The request content type, if known
     */
    public function getContentType()
    {
        $result = $this->getHeader('Content-Type');

        return $result ? $result[0] : null;
    }

    /**
     * Get request media type, if known.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string|null The request media type, minus content-type params
     */
    public function getMediaType()
    {
        $contentType = $this->getContentType();
        if ($contentType) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);

            return strtolower($contentTypeParts[0]);
        }

        return null;
    }

    /**
     * Get request media type params, if known.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return array
     */
    public function getMediaTypeParams()
    {
        $contentType = $this->getContentType();
        $contentTypeParams = array();

        if ($contentType) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);
            $contentTypePartsLength = count($contentTypeParts);

            for ($i = 1; $i < $contentTypePartsLength; $i++) {
                $paramParts = explode('=', $contentTypeParts[$i]);
                $contentTypeParams[strtolower($paramParts[0])] = $paramParts[1];
            }
        }

        return $contentTypeParams;
    }

    /**
     * Get request content character set, if known.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string|null
     */
    public function getContentCharset()
    {
        $mediaTypeParams = $this->getMediaTypeParams();

        if (isset($mediaTypeParams['charset'])) {
            return $mediaTypeParams['charset'];
        }

        return null;
    }

    /**
     * Get request content length, if known.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return int|null
     */
    public function getContentLength()
    {
        $result = $this->headerParams->get('Content-Length');

        return $result ? (int)$result[0] : null;
    }

    /*******************************************************************************
     * Cookies
     ******************************************************************************/

    /**
     * Retrieve cookies.
     *
     * Retrieves cookies sent by the client to the server.
     *
     * The data MUST be compatible with the structure of the $_COOKIE
     * superglobal.
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->cookieParams->all();
    }

    /**
     * Return cookies-bag
     *
     * @return Bag
     */
    public function getCookieparamsBag()
    {
        return $this->cookieParams;
    }

    /**
     * Return an instance with the specified cookies.
     *
     * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
     * be compatible with the structure of $_COOKIE. Typically, this data will
     * be injected at instantiation.
     *
     * This method MUST NOT update the related Cookie header of the request
     * instance, nor related values in the server params.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated cookie values.
     *
     * @param array $cookies Array of key/value pairs representing cookies.
     * @return self
     */
    public function withCookieParams(array $cookies)
    {
        $clone = clone $this;
        $clone->cookieParams = new Bag($cookies);

        return $clone;
    }

    /*******************************************************************************
     * Query Params
     ******************************************************************************/

    /**
     * Retrieve query string arguments.
     *
     * Retrieves the deserialized query string arguments, if any.
     *
     * Note: the query params might not be in sync with the URI or server
     * params. If you need to ensure you are only getting the original
     * values, you may need to parse the query string from `getUri()->getQuery()`
     * or from the `QUERY_STRING` server param.
     *
     * @return array
     */
    public function getQueryParams()
    {
        if ($this->queryParams) {
            return $this->queryParams;
        }

        if ($this->uri === null) {
            return array();
        }

        parse_str($this->uri->getQuery(), $query); // <-- URL decodes data
        $this->queryParams->add($query);

        return $this->queryParams->all();
    }

    /**
     * Get the query-bag
     *
     * @return Bag
     */
    public function getQueryParamsBag()
    {
        return $this->queryParams;
    }

    /**
     * Return an instance with the specified query string arguments.
     *
     * These values SHOULD remain immutable over the course of the incoming
     * request. They MAY be injected during instantiation, such as from PHP's
     * $_GET superglobal, or MAY be derived from some other value such as the
     * URI. In cases where the arguments are parsed from the URI, the data
     * MUST be compatible with what PHP's parse_str() would return for
     * purposes of how duplicate query parameters are handled, and how nested
     * sets are handled.
     *
     * Setting query string arguments MUST NOT change the URI stored by the
     * request, nor the values in the server params.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated query string arguments.
     *
     * @param array $query Array of query string arguments, typically from
     *     $_GET.
     * @return self
     */
    public function withQueryParams(array $query)
    {
        $clone = clone $this;
        $clone->queryParams = new Bag($query);

        return $clone;
    }

    /*******************************************************************************
     * File Params
     ******************************************************************************/

    /**
     * Retrieve normalized file upload data.
     *
     * This method returns upload metadata in a normalized tree, with each leaf
     * an instance of Psr\Http\Message\UploadedFileInterface.
     *
     * These values MAY be prepared from $_FILES or the message body during
     * instantiation, or MAY be injected via withUploadedFiles().
     *
     * @return array An array tree of UploadedFileInterface instances; an empty
     *     array MUST be returned if no data is present.
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles->all();
    }

    /**
     * @return UploadedFileBag
     */
    public function getUploadedFilesBag()
    {
        return $this->uploadedFiles;
    }

    /**
     * Create a new instance with the specified uploaded files.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated body parameters.
     *
     * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
     * @return self
     * @throws \InvalidArgumentException if an invalid structure is provided.
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $clone = clone $this;
        $clone->uploadedFiles = new UploadedFileBag($uploadedFiles);

        return $clone;
    }

    /*******************************************************************************
     * Server Params
     ******************************************************************************/

    /**
     * Retrieve server parameters.
     *
     * Retrieves data related to the incoming request environment,
     * typically derived from PHP's $_SERVER superglobal. The data IS NOT
     * REQUIRED to originate from $_SERVER.
     *
     * @return array
     */
    public function getServerParams()
    {
        return $this->serverParams->all();
    }

    /**
     * @return ServerBag
     */
    public function getServerParamsBag()
    {
        return $this->serverParams;
    }

    /*******************************************************************************
     * Attributes
     ******************************************************************************/

    /**
     * Retrieve attributes derived from the request.
     *
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return array Attributes derived from the request.
     */
    public function getAttributes()
    {
        return $this->attributes->all();
    }

    /**
     * @return Bag
     */
    public function getAttributesBag()
    {
        return $this->attributes;
    }

    /**
     * Retrieve a single derived request attribute.
     *
     * Retrieves a single derived request attribute as described in
     * getAttributes(). If the attribute has not been previously set, returns
     * the default value as provided.
     *
     * This method obviates the need for a hasAttribute() method, as it allows
     * specifying a default value to return if the attribute is not found.
     *
     * @see getAttributes()
     * @param string $name The attribute name.
     * @param mixed $default Default value to return if the attribute does not exist.
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributes->get($name, $default);
    }

    /**
     * Return an instance with the specified derived request attribute.
     *
     * This method allows setting a single derived request attribute as
     * described in getAttributes().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated attribute.
     *
     * @see getAttributes()
     * @param string $name The attribute name.
     * @param mixed $value The value of the attribute.
     * @return self
     */
    public function withAttribute($name, $value)
    {
        $clone = clone $this;
        $clone->attributes->set($name, $value);

        return $clone;
    }

    /**
     * Create a new instance with the specified derived request attributes.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method allows setting all new derived request attributes as
     * described in getAttributes().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * updated attributes.
     *
     * @param  array $attributes New attributes
     * @return self
     */
    public function withAttributes(array $attributes)
    {
        $clone = clone $this;
        $clone->attributes = new Bag($attributes);

        return $clone;
    }

    /**
     * Return an instance that removes the specified derived request attribute.
     *
     * This method allows removing a single derived request attribute as
     * described in getAttributes().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the attribute.
     *
     * @see getAttributes()
     * @param string $name The attribute name.
     * @return self
     */
    public function withoutAttribute($name)
    {
        $clone = clone $this;
        $clone->attributes->remove($name);

        return $clone;
    }

    /*******************************************************************************
     * Body
     ******************************************************************************/

    /**
     * Retrieve any parameters provided in the request body.
     *
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, this method MUST
     * return the contents of $_POST.
     *
     * Otherwise, this method may return any results of deserializing
     * the request body content; as parsing returns structured content, the
     * potential types MUST be arrays or objects only. A null value indicates
     * the absence of body content.
     *
     * @return null|array|object The deserialized body parameters, if any.
     *     These will typically be an array or object.
     * @throws \RuntimeException if the request body media type parser returns an invalid value
     */
    public function getParsedBody()
    {
        if ($this->bodyParsed) {
            return $this->bodyParsed;
        }

        if (!$this->body) {
            return null;
        }

        $mediaType = $this->getMediaType();
        $body = (string)$this->getBody();

        if (isset($this->bodyParsers[$mediaType]) === true) {
            $parsed = $this->bodyParsers[$mediaType]($body);

            if (!is_null($parsed) && !is_object($parsed) && !is_array($parsed)) {
                throw new \RuntimeException(
                    'Request body media type parser return value must be an array, an object, or null'
                );
            }

            $this->bodyParsed = $parsed;
        }

        return $this->bodyParsed;
    }

    /**
     * Return an instance with the specified body parameters.
     *
     * These MAY be injected during instantiation.
     *
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, use this method
     * ONLY to inject the contents of $_POST.
     *
     * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
     * deserializing the request body content. Deserialization/parsing returns
     * structured data, and, as such, this method ONLY accepts arrays or objects,
     * or a null value if nothing was available to parse.
     *
     * As an example, if content negotiation determines that the request data
     * is a JSON payload, this method could be used to create a request
     * instance with the deserialized parameters.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated body parameters.
     *
     * @param null|array|object $data The deserialized body data. This will
     *     typically be in an array or object.
     * @return self
     * @throws \InvalidArgumentException if an unsupported argument type is
     *     provided.
     */
    public function withParsedBody($data)
    {
        if (!is_null($data) && !is_object($data) && !is_array($data)) {
            throw new \InvalidArgumentException('Parsed body value must be an array, an object, or null');
        }

        $clone = clone $this;
        $clone->bodyParsed = $data;

        return $clone;
    }

    /**
     * Register media type parser.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param string   $mediaType A HTTP media type (excluding content-type
     *     params).
     * @param callable $callable  A callable that returns parsed contents for
     *     media type.
     */
    public function registerMediaTypeParser($mediaType, $callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Argument 2 must be callable.');
        }

        $this->bodyParsers[(string)$mediaType] = $callable;
    }

    /*******************************************************************************
     * Parameters (e.g., POST and GET data)
     ******************************************************************************/

    /**
     * Fetch request parameter value from body or query string (in that order).
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param  string $key The parameter key.
     * @param  string $default The default value.
     *
     * @return mixed The parameter value.
     */
    public function getParam($key, $default = null)
    {
        $postParams = $this->getParsedBody();

        if (is_array($postParams) && isset($postParams[$key])) {
            return $postParams[$key];
        }

        if (is_object($postParams) && property_exists($postParams, $key)) {
            return $postParams->$key;
        }

        return $this->queryParams->get($key, $default);
    }

    /**
     * Fetch parameter value from request body.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    public function getParsedBodyParam($key, $default = null)
    {
        $postParams = $this->getParsedBody();
        $result = $default;
        if (is_array($postParams) && isset($postParams[$key])) {
            $result = $postParams[$key];
        } elseif (is_object($postParams) && property_exists($postParams, $key)) {
            $result = $postParams->$key;
        }

        return $result;
    }

    /**
     * Fetch parameter value from query string.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    public function getQueryParam($key, $default = null)
    {
        return $this->queryParams->get($key, $default);
    }

    /**
     * Fetch assocative array of body and query string parameters.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return array
     */
    public function getParams()
    {
        $params = $this->getQueryParams();
        $postParams = $this->getParsedBody();

        if ($postParams) {
            $params = array_merge($params, (array)$postParams);
        }

        return $params;
    }
}
