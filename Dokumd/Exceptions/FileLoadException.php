<?php

namespace Dokumd\Exceptions;

use Exception;
use Throwable;

/**
 * Class FileLoadException
 * @package Dokumd\Exceptions
 */
class FileLoadException extends Exception
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
        $message = sprintf('Unable to load \"%s\". ' . $message, $filename);
        parent::__construct($message, $code, $previous);
    }

}