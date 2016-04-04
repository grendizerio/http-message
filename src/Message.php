<?php

namespace Grendizer\HttpMessage;

/**
 * Abstract message (base class for Request and Response)
 *
 * This class represents a general HTTP message. It provides common properties and methods for
 * the HTTP request and response, as defined in the PSR-7 MessageInterface.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/MessageInterface.php
 */
abstract class Message implements MessageInterface
{
    /**
     * Protocol version
     *
     * @var string
     */
    protected $protocolVersion = '1.1';

    /**
     * Headers
     *
     * @var HeaderBag
     */
    protected $headerParams;

    /**
     * Body object
     *
     * @var StreamInterface
     */
    protected $body;


    /*******************************************************************************
     * Protocol
     ******************************************************************************/

    /**
     * @inheritdoc
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * @inheritdoc
     */
    public function withProtocolVersion($version)
    {
        static $valid = array(
            '1.0' => true,
            '1.1' => true,
            '2.0' => true,
        );
        if (!isset($valid[$version])) {
            throw new \InvalidArgumentException('Invalid HTTP version. Must be one of: 1.0, 1.1, 2.0');
        }
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /*******************************************************************************
     * headerParams
     ******************************************************************************/

    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        return $this->headerParams->all();
    }

    /**
     * @return HeaderBag
     */
    public function getHeadersBag()
    {
        return $this->headerParams;
    }

    /**
     * @inheritdoc
     */
    public function hasHeader($name)
    {
        return $this->headerParams->has($name);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($name)
    {
        return $this->headerParams->get($name, array());
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name)
    {
        return implode(',', $this->headerParams->get($name, array()));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value)
    {
        $clone = clone $this;
        $clone->headerParams->set($name, $value);

        return $clone;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($name, $value)
    {
        $clone = clone $this;
        $clone->headerParams->add($name, $value);

        return $clone;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return static
     */
    public function withoutHeader($name)
    {
        $clone = clone $this;
        $clone->headerParams->remove($name);

        return $clone;
    }

    /*******************************************************************************
     * Body
     ******************************************************************************/

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param  StreamInterface $body Body.
     * @return static
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body)
    {
        // TODO: Test for invalid body?
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }
}
