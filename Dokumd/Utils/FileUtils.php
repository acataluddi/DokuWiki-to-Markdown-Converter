<?php

namespace Dokumd\Utils;

use Dokumd\Exceptions\FileException;
use Dokumd\Exceptions\FileLoadException;

/**
 * Class FileUtils
 * Service methods to handle files.
 *
 * @package Dokumd\Utils
 * @author Adriano Cataluddi <acataluddi@gmail.com>
 */
class FileUtils
{
    /**
     * Copies the $source file to $dest
     * @param string $source The source file.
     * @param string $dest The destination
     * @return void
     * @throws FileException
     */
    public static function copy(string $source, string $dest)
    {
        if (copy($source, $dest) === false)
            throw new FileException($source, sprintf('Unable to copy the file to "%s"', $dest));
    }

    /**
     * @param string $file
     * @return string
     * @throws FileLoadException
     */
    public static function load(string $file): string
    {
        if (!file_exists($file))
            throw new FileLoadException($file);

        $contents = file_get_contents($file);
        if ($contents === false)
            throw new FileLoadException($file);

        return $contents;
    }
}