<?php

namespace Grendizer\HttpMessage;

/**
 * Represents Uploaded Files.
 *
 * It manages and normalizes uploaded files according to the PSR-7 standard.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/UploadedFileInterface.php
 * @link https://github.com/php-fig/http-message/blob/master/src/StreamInterface.php
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * The full path to the uploaded file provided by the client.
     *
     * @var string
     */
    protected $file;

    /**
     * The client-provided file name.
     *
     * @var string
     */
    protected $name;
    /**
     * The client-provided media type of the file.
     *
     * @var string
     */
    protected $type;
    /**
     * The size of the file in bytes.
     *
     * @var int
     */
    protected $size;
    /**
     * A valid PHP UPLOAD_ERR_xxx code for the file upload.
     *
     * @var int
     */
    protected $error = UPLOAD_ERR_OK;
    /**
     * Indicates if the upload is from a SAPI environment.
     *
     * @var bool
     */
    protected $sapi = false;
    /**
     * An optional StreamInterface wrapping the file resource.
     *
     * @var \Grendizer\MicroFramework\Interfaces\Http\StreamInterface
     */
    protected $stream;
    /**
     * Indicates if the uploaded file has already been moved.
     *
     * @var bool
     */
    protected $moved = false;

    /**
     * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file data.
     *
     * @param array $uploadedFiles The non-normalized tree of uploaded file data.
     *
     * @return array A normalized tree of UploadedFile instances.
     */
    public static function parseUploadedFiles(array $uploadedFiles)
    {
        $parsed = array();
        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (!isset($uploadedFile['error'])) {
                if (is_array($uploadedFile)) {
                    $parsed[$field] = static::parseUploadedFiles($uploadedFile);
                }
                
                continue;
            }
            
            $parsed[$field] = array();
            if (!is_array($uploadedFile['error'])) {
                $parsed[$field] = new static(
                    $uploadedFile['tmp_name'],
                    isset($uploadedFile['name']) ? $uploadedFile['name'] : null,
                    isset($uploadedFile['type']) ? $uploadedFile['type'] : null,
                    isset($uploadedFile['size']) ? $uploadedFile['size'] : null,
                    $uploadedFile['error'],
                    true
                );
            } else {
                foreach ($uploadedFile['error'] as $fileIdx => $error) {
                    $parsed[$field][] = new static(
                        $uploadedFile['tmp_name'][$fileIdx],
                        isset($uploadedFile['name']) ? $uploadedFile['name'][$fileIdx] : null,
                        isset($uploadedFile['type']) ? $uploadedFile['type'][$fileIdx] : null,
                        isset($uploadedFile['size']) ? $uploadedFile['size'][$fileIdx] : null,
                        $uploadedFile['error'][$fileIdx],
                        true
                    );
                }
            }
        }

        return $parsed;
    }

    /**
     * Construct a new UploadedFile instance.
     *
     * @param string      $file The full path to the uploaded file provided by the client.
     * @param string|null $name The file name.
     * @param string|null $type The file media type.
     * @param int|null    $size The file size in bytes.
     * @param int         $error The UPLOAD_ERR_XXX code representing the status of the upload.
     * @param bool        $sapi Indicates if the upload is in a SAPI environment.
     */
    public function __construct($file, $name = null, $type = null, $size = null, $error = UPLOAD_ERR_OK, $sapi = false)
    {
        $this->file = $file;
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->error = $error;
        $this->sapi = $sapi;
    }

    /**
     * @inheritdoc
     */
    public function getStream()
    {
        if ($this->moved) {
            throw new \RuntimeException(sprintf('Uploaded file %1s has already been moved', $this->name));
        }
        if ($this->stream === null) {
            $this->stream = new Stream(fopen($this->file, 'r'));
        }

        return $this->stream;
    }

    /**
     * @inheritdoc
     */
    public function moveTo($targetPath)
    {
        if ($this->moved) {
            throw new \RuntimeException('Uploaded file already moved');
        }

        if (!is_writable(dirname($targetPath))) {
            throw new \InvalidArgumentException('Upload target path is not writable');
        }

        $targetIsStream = strpos($targetPath, '://') > 0;
        
        if ($targetIsStream) {
            if (!copy($this->file, $targetPath)) {
                throw new \RuntimeException(sprintf('Error moving uploaded file %1s to %2s', $this->name, $targetPath));
            }
            
            if (!unlink($this->file)) {
                throw new \RuntimeException(sprintf('Error removing uploaded file %1s', $this->name));
            }
        } elseif ($this->sapi) {
            if (!is_uploaded_file($this->file)) {
                throw new \RuntimeException(sprintf('%1s is not a valid uploaded file', $this->file));
            }

            if (!move_uploaded_file($this->file, $targetPath)) {
                throw new \RuntimeException(sprintf('Error moving uploaded file %1s to %2s', $this->name, $targetPath));
            }
        } else {
            if (!rename($this->file, $targetPath)) {
                throw new \RuntimeException(sprintf('Error moving uploaded file %1s to %2s', $this->name, $targetPath));
            }
        }

        $this->moved = true;
    }

    /**
     * @inheritdoc
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function getClientFilename()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getClientMediaType()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function getSize()
    {
        return $this->size;
    }
}
