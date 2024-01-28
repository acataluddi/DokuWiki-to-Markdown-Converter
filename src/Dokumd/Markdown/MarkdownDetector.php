<?php

namespace Dokumd\Markdown;

use Dokumd\Exceptions\FileLoadException;
use Dokumd\Utils\FileUtils;

/**
 * Class MarkdownDetector
 * A simple class to detect whether the given file contains Markdown
 *
 * @package Dokumd\Utils
 * @author Adriano Cataluddi <acataluddi@gmail.com>
 */
class MarkdownDetector
{
    /**
     * Returns true if in the given file are detected fragments of Markdown
     * @param string $file
     * @return bool
     * @throws FileLoadException
     */
    public function containsMarkdown(string $file): bool
    {
        $lines = DocuwikiToMarkdownExtra::getLines(FileUtils::load($file));
        foreach ($lines as $line) {
            if ($this->hasCodeBlock($line))
                return true;

            if ($this->hasInlineHeader($line))
                return true;

            if ($this->hasMultilineHeader($line))
                return true;
        }
        return false;
    }

    /**
     * Returns true if there is a ``` code block
     * @param string $line
     * @return bool
     */
    protected function hasCodeBlock(string $line): bool
    {
        return strpos($line, '```') !== false;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected function hasInlineHeader(string $line): bool
    {
        $inlineHeaderPattern = '/^#+\s+.*/';
        $count = preg_match($inlineHeaderPattern, $line);
        if ($count === false)
            return false;

        return $count > 0;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected function hasMultilineHeader(string $line): bool
    {
        $multilineHeaderPattern = '/^[=-]+$/m';
        $count = preg_match($multilineHeaderPattern, $line);
        if ($count === false)
            return false;

        return $count > 0;
    }
}