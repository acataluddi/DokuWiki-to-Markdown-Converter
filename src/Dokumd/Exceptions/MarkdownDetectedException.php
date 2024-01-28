<?php

namespace Dokumd\Exceptions;

use Exception;
use Throwable;

/**
 * Class MarkdownDetectedException
 * @package Dokumd\Exceptions
 */
class MarkdownDetectedException extends Exception
{
    /**
     * @var string
     */
    protected string $filename;
    /**
     * @var int
     */
    protected int $inputLine = 0;

    /**
     * @param string $filename
     * @param int $line
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $filename, int $line, string $message = '', int $code = 0, Throwable $previous = null)
    {
        $this->filename = $filename;
        $message = sprintf('Markdown detected at \"%s:%s\". ' . $message, $filename, $line);
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return int
     */
    public function getInputLine(): int
    {
        return $this->inputLine;
    }
}