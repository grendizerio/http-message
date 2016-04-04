<?php

namespace Grendizer\HttpMessage;

/**
 * Bag is a container for key/value pairs.
 */
interface BagInterface extends \IteratorAggregate, \Countable
{
    /**
     * Returns the parameters.
     *
     * @return array An array of parameters
     */
    public function all();

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys();

    /**
     * Replaces the current parameters by a new set.
     *
     * @param array $parameters An array of parameters
     */
    public function replace(array $parameters = array());

    /**
     * Adds parameters.
     *
     * @param  string|array  $key
     * @param  mixed $value
     */
    public function add($key, $value = null);

    /**
     * Returns a parameter by name.
     *
     * @param string $key     The key
     * @param mixed  $default The default value if the parameter key does not exist
     *
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Sets a parameter by name.
     *
     * @param string $key   The key
     * @param mixed $value The value
     * @param bool $replace Replace old value
     */
    public function set($key, $value, $replace = true);

    /**
     * Returns true if the parameter is defined.
     *
     * @param string $key The key
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has($key);

    /**
     * Removes a parameter.
     *
     * @param string $key The key
     */
    public function remove($key);

    /**
     * Filter key.
     *
     * @param string $key     Key.
     * @param mixed  $default Default = null.
     * @param int    $filter  FILTER_* constant.
     * @param mixed  $options Filter options.
     *
     * @see http://php.net/manual/en/function.filter-var.php
     *
     * @return mixed
     */
    public function filter($key, $default = null, $filter = FILTER_DEFAULT, $options = array());
}
