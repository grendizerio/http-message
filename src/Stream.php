<?php

namespace Grendizer\HttpMessage;

/**
 * Represents a data stream as defined in PSR-7.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/StreamInterface.php
 */
class Stream implements StreamInterface
{
    /**
     * Resource modes
     *
     * @var  array
     * @link http://php.net/manual/function.fopen.php
     */
    protected static $modes = array(
        'readable' => array('r', 'r+', 'w+', 'a+', 'x+', 'c+'),
        'writable' => array('r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'),
    );

    /**
     * The underlying stream resource
     *
     * @var resource
     */
    protected $stream;

    /**
     * Stream metadata
     *
     * @var array
     */
    protected $meta;

    /**
     * Is this stream readable?
     *
     * @var bool
     */
    protected $readable;

    /**
     * Is this stream writable?
     *
     * @var bool
     */
    protected $writable;

    /**
     * Is this stream seekable?
     *
     * @var bool
     */
    protected $seekable;

    /**
     * The size of the stream if known
     *
     * @var null|int
     */
    protected $size;

    /**
     * Create a new Stream.
     *
     * @param  resource $stream A PHP resource handle.
     *
     * @throws \InvalidArgumentException If argument is not a resource.
     */
    public function __construct($stream)
    {
        $this->attach($stream);
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     *
     * @param string $key Specific metadata to retrieve.
     *
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        $this->meta = stream_get_meta_data($this->stream);
        if (is_null($key) === true) {
            return $this->meta;
        }

        return isset($this->meta[$key]) ? $this->meta[$key] : null;
    }

    /**
     * Is a resource attached to this stream?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    protected function isAttached()
    {
        return is_resource($this->stream);
    }

    /**
     * Attach new resource to this object.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param resource $newStream A PHP resource handle.
     *
     * @throws \InvalidArgumentException If argument is not a valid PHP resource.
     */
    protected function attach($newStream)
    {
        if (is_resource($newStream) === false) {
            throw new \InvalidArgumentException(__METHOD__ . ' argument must be a valid PHP resource');
        }

        if ($this->isAttached() === true) {
            $this->detach();
        }

        $this->stream = $newStream;
    }

    /**
     * @inheritdoc
     */
    public function detach()
    {
        $oldResource = $this->stream;
        $this->stream = null;
        $this->meta = null;
        $this->readable = null;
        $this->writable = null;
        $this->seekable = null;
        $this->size = null;

        return $oldResource;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        if (!$this->isAttached()) {
            return '';
        }

        try {
            $this->rewind();
            return $this->getContents();
        } catch (\RuntimeException $e) {
            return '';
        }
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        if ($this->isAttached() === true) {
            fclose($this->stream);
        }

        $this->detach();
    }

    /**
     * @inheritdoc
     */
    public function getSize()
    {
        if (!$this->size && $this->isAttached() === true) {
            $stats = fstat($this->stream);
            $this->size = isset($stats['size']) ? $stats['size'] : null;
        }

        return $this->size;
    }

    /**
     * @inheritdoc
     */
    public function tell()
    {
        if (!$this->isAttached() || ($position = ftell($this->stream)) === false) {
            throw new \RuntimeException('Could not get the position of the pointer in stream');
        }

        return $position;
    }

    /**
     * @inheritdoc
     */
    public function eof()
    {
        return $this->isAttached() ? feof($this->stream) : true;
    }

    /**
     * @inheritdoc
     */
    public function isReadable()
    {
        if ($this->readable === null) {
            $this->readable = false;
            if ($this->isAttached()) {
                $meta = $this->getMetadata();
                foreach (self::$modes['readable'] as $mode) {
                    if (strpos($meta['mode'], $mode) === 0) {
                        $this->readable = true;
                        break;
                    }
                }
            }
        }

        return $this->readable;
    }

    /**
     * @inheritdoc
     */
    public function isWritable()
    {
        if ($this->writable === null) {
            $this->writable = false;
            if ($this->isAttached()) {
                $meta = $this->getMetadata();
                foreach (self::$modes['writable'] as $mode) {
                    if (strpos($meta['mode'], $mode) === 0) {
                        $this->writable = true;
                        break;
                    }
                }
            }
        }

        return $this->writable;
    }

    /**
     * @inheritdoc
     */
    public function isSeekable()
    {
        if ($this->seekable === null) {
            $this->seekable = false;
            if ($this->isAttached()) {
                $meta = $this->getMetadata();
                $this->seekable = $meta['seekable'];
            }
        }

        return $this->seekable;
    }

    /**
     * @inheritdoc
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        // Note that fseek returns 0 on success!
        if (!$this->isSeekable() || fseek($this->stream, $offset, $whence) === -1) {
            throw new \RuntimeException('Could not seek in stream');
        }
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        if (!$this->isSeekable() || rewind($this->stream) === false) {
            throw new \RuntimeException('Could not rewind stream');
        }
    }

    /**
     * @inheritdoc
     */
    public function read($length)
    {
        if (!$this->isReadable() || ($data = fread($this->stream, $length)) === false) {
            throw new \RuntimeException('Could not read from stream');
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function write($string)
    {
        if (!$this->isWritable() || ($written = fwrite($this->stream, $string)) === false) {
            throw new \RuntimeException('Could not write to stream');
        }

        // reset size so that it will be recalculated on next call to getSize()
        $this->size = null;

        return $written;
    }

    /**
     * @inheritdoc
     */
    public function getContents()
    {
        if (!$this->isReadable() || ($contents = stream_get_contents($this->stream)) === false) {
            throw new \RuntimeException('Could not get contents of stream');
        }

        return $contents;
    }
}
