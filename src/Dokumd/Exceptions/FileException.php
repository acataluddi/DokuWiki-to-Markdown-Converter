<?php

namespace Dokumd\Exceptions;

use Exception;
use Throwable;

/**
 * Class FileLoadException
 * @package Dokumd\Exceptions
 */
class FileException extends Exception
{
    /**
     * @var string
     */
    protected string $filename;

    /**
     * @param string $filename
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $filename, string $message = '', int $code = 0, Throwable $previous = null)
    {
        $this->filename = $filename;
        $message = sprintf('Error with file \"%s\". ' . $message, $filename);
        parent::__construct($message, $code, $previous);
    }

}