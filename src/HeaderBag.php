<?php

namespace Grendizer\HttpMessage;


class HeaderBag implements BagInterface
{
    protected $headers = array();
    protected $cacheControl = array();

    /**
     * Special HTTP headers that do not have the "HTTP_" prefix
     *
     * @var array
     */
    protected static $special = array(
        'CONTENT_TYPE' => 1,
        'CONTENT_LENGTH' => 1,
        'PHP_AUTH_USER' => 1,
        'PHP_AUTH_PW' => 1,
        'PHP_AUTH_DIGEST' => 1,
        'AUTH_TYPE' => 1,
    );

    /**
     * Create new collection
     *
     * @param array $headers Pre-populate collection with this key-value array
     */
    public function __construct(array $headers = array())
    {
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
    }
    
    public function __toString()
    {
        if (!$this->headers) {
            return '';
        }

        $max = max(array_map('strlen', array_keys($this->headers))) + 1;
        $content = '';
        ksort($this->headers);
        foreach ($this->headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));
            foreach ($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
            }
        }

        return $content;
    }

    /**
     * Return array of HTTP header names and values.
     * This method returns the _original_ header name
     * as specified by the end user.
     *
     * @return array
     */
    public function all()
    {
        $out = array();
        
        foreach ($this->headers as $key => $props) {
            $out[$props['originalKey']] = $props['value'];
        }

        return $out;
    }

    /**
     * Retuen array of HTTP header names.
     * 
     * @return array
     */
    public function keys()
    {
        return array_keys($this->headers);
    }

    /**
     * Set HTTP header value
     *
     * This method sets a header value. It replaces
     * any values that may already exist for the header name.
     *
     * @param string $key   The case-insensitive header name
     * @param string $value The header value
     * @param bool $replace Replace old value
     */
    public function set($key, $value, $replace = true)
    {
        if (!is_array($value)) {
            $value = array($value);
        }

        $oldValue = $this->get($key);

        if ($replace && !empty($oldValue) && is_array($oldValue)) {
            $value = array_replace($oldValue, $value);
        }
        
        $normalizeKey = $this->normalizeKey($key);

        $this->headers[$normalizeKey] = array(
            'value' => $value,
            'originalKey' => $key
        );

        if ('cache-control' === $normalizeKey) {
            $this->cacheControl = $this->parseCacheControl($value[0]);
        }
    }

    /**
     * Get HTTP header value
     *
     * @param  string  $key     The case-insensitive header name
     * @param  mixed   $default The default value if key does not exist
     *
     * @return string[]
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            $result = $this->headers[$this->normalizeKey($key)];
            return $result['value'];
        }

        return $default;
    }

    /**
     * Get HTTP header key as originally specified
     *
     * @param  string   $key     The case-insensitive header name
     * @param  mixed    $default The default value if key does not exist
     *
     * @return string
     */
    public function getOriginalKey($key, $default = null)
    {
        if ($this->has($key)) {
            $result = $this->headers[$this->normalizeKey($key)];
            return $result['originalKey'];
        }

        return $default;
    }

    /**
     * Add HTTP header value
     *
     * This method appends a header value. Unlike the set() method,
     * this method _appends_ this new value to any values
     * that already exist for this header name.
     *
     * @param string       $key   The case-insensitive header name
     * @param array|string $value The new header value(s)
     */
    public function add($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->add($k, $v);
            }
        } elseif ($value === null) {
            $this->remove($key);
        } else {
            $oldValues = $this->get($key, array());
            $newValues = is_array($value) ? $value : array($value);
            $this->set($key, array_merge($oldValues, array_values($newValues)));
        }
    }
    
    /**
     * Does this collection have a given header?
     *
     * @param  string $key The case-insensitive header name
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($this->normalizeKey($key), $this->headers);
    }

    /**
     * Remove header from collection
     *
     * @param  string $key The case-insensitive header name
     */
    public function remove($key)
    {
        unset($this->headers[$key = $this->normalizeKey($key)]);
        
        if ('cache-control' === $key) {
            $this->cacheControl = array();
        }
    }

    /**
     * @inheritdoc
     */
    public function replace(array $parameters = array())
    {
        $this->headers = array();
        
        foreach ($parameters as $key=>$value) {
            $this->set($key, $value);
        }
    }

    /**
     * Normalize header name
     *
     * This method transforms header names into a
     * normalized form. This is how we enable case-insensitive
     * header names in the other methods in this class.
     *
     * @param  string $key The case-insensitive header name
     *
     * @return string Normalized header name
     */
    public function normalizeKey($key)
    {
        $key = strtr(strtolower($key), '_', '-');
        if (strpos($key, 'http-') === 0) {
            $key = substr($key, 5);
        }

        return $key;
    }

    /**
     * @inheritdoc
     */
    public function filter($key, $default = null, $filter = FILTER_DEFAULT, $options = array())
    {
        $value = $this->get($key, $default);

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (!is_array($options) && $options) {
            $options = array('flags' => $options);
        }

        // Add a convenience check for arrays.
        if (is_array($value) && !isset($options['flags'])) {
            $options['flags'] = FILTER_REQUIRE_ARRAY;
        }

        return filter_var($value, $filter, $options);
    }

    /**
     * Returns the HTTP header value converted to a date.
     *
     * @param string    $key     The parameter key
     * @param \DateTime $default The default value
     *
     * @return null|\DateTime The parsed DateTime or the default value if the header does not exist
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function getDate($key, \DateTime $default = null)
    {
        if (null === $value = $this->get($key)) {
            return $default;
        }

        if (false === $date = \DateTime::createFromFormat(DATE_RFC2822, $value)) {
            throw new \RuntimeException(sprintf('The %s HTTP header is not parseable (%s).', $key, $value));
        }

        return $date;
    }

    /**
     * Adds a custom Cache-Control directive.
     *
     * @param string $key   The Cache-Control directive name
     * @param mixed  $value The Cache-Control directive value
     */
    public function addCacheControlDirective($key, $value = true)
    {
        $this->cacheControl[$key] = $value;

        $this->set('Cache-Control', $this->getCacheControlHeader());
    }

    /**
     * Returns true if the Cache-Control directive is defined.
     *
     * @param string $key The Cache-Control directive
     *
     * @return bool true if the directive exists, false otherwise
     */
    public function hasCacheControlDirective($key)
    {
        return array_key_exists($key, $this->cacheControl);
    }

    /**
     * Returns a Cache-Control directive value by name.
     *
     * @param string $key The directive name
     *
     * @return mixed|null The directive value if defined, null otherwise
     */
    public function getCacheControlDirective($key)
    {
        return array_key_exists($key, $this->cacheControl) ? $this->cacheControl[$key] : null;
    }

    /**
     * Removes a Cache-Control directive.
     *
     * @param string $key The Cache-Control directive
     */
    public function removeCacheControlDirective($key)
    {
        unset($this->cacheControl[$key]);

        $this->set('Cache-Control', $this->getCacheControlHeader());
    }

    /**
     * Returns an iterator for parameters.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->headers);
    }

    /**
     * Returns the number of parameters.
     *
     * @return int The number of parameters
     */
    public function count()
    {
        return count($this->headers);
    }
    
    protected function getCacheControlHeader()
    {
        $parts = array();
        ksort($this->cacheControl);
        foreach ($this->cacheControl as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"'.$value.'"';
                }

                $parts[] = "$key=$value";
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Parses a Cache-Control HTTP header.
     *
     * @param string $header The value of the Cache-Control HTTP header
     *
     * @return array An array representing the attribute values
     */
    protected function parseCacheControl($header)
    {
        $cacheControl = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[strtolower($match[1])] = isset($match[3]) ? $match[3] : (isset($match[2]) ? $match[2] : true);
        }

        return $cacheControl;
    }
}