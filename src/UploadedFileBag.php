<?php

namespace Grendizer\HttpMessage;

class UploadedFileBag implements BagInterface
{
    /**
     * @var array
     */
    protected $files;

    /**
     * UploadedFileBag constructor.
     * 
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        $this->files = UploadedFile::parseUploadedFiles($parameters);
    }

    /**
     * @inheritdoc
     */
    public function all()
    {
        return $this->files;
    }

    /**
     * @inheritdoc
     */
    public function keys()
    {
        return array_keys($this->files);
    }

    /**
     * @inheritdoc
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->files) ? $this->files[$key] : $default;
    }

    /**
     * @inheritdoc
     */
    public function has($key)
    {
        return array_key_exists($key, $this->files);
    }

    /**
     * @inheritdoc
     */
    public function remove($key)
    {
        unset($this->files[$key]);
    }

    /**
     * @inheritdoc
     */
    public function replace(array $files = array())
    {
        $this->files = UploadedFile::parseUploadedFiles($files);
    }

    /**
     * @inheritdoc
     */
    public function add($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $this->set($name, $value);
            }
        } else {
            $this->set($key, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value, $replace = true)
    {
        $values = UploadedFile::parseUploadedFiles(array($key=>$value));
        if (!empty($values)) {
            $this->files[$key] = $value;
        }
    }

    /**
     * @inheritdoc
     * @todo 需要确定该功能是否有效？
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
     * Returns an iterator for parameters.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->files);
    }

    /**
     * Returns the number of parameters.
     *
     * @return int The number of parameters
     */
    public function count()
    {
        return count($this->files);
    }
}
