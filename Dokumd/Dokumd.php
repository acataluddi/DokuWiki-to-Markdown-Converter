<?php

namespace Dokumd;

use Dokumd\Exceptions\FileLoadException;
use Dokumd\Markdown\DocuwikiToMarkdownExtra;
use Dokumd\Utils\Console;
use Dokumd\Utils\Constants;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Class Converter
 * @package Dokumd
 * @author Mark Stephens, Ingo Schommer
 * @author Adriano Cataluddi
 */
class Dokumd
{
    const EXIT_SUCCESS = 0;
    const EXIT_FAILURE = 1;

    /**
     * The script entrypoint
     * @return void
     */
    public static function main()
    {
        (new Dokumd())->run();
    }

    /**
     * @return void
     */
    public function run()
    {
        try {
            global $argv;
            if (count($argv) < 3) {
                $this->showHelp();
                exit (self::EXIT_FAILURE);
            }

            $inputDir = $argv[1];
            $outputDir = $argv[2];
            $template = (isset($argv[3])) ? $this->loadContents($argv[3]) : false;

            foreach ([$inputDir, $outputDir] as $path) {
                if (!is_dir($path))
                    throw new RuntimeException(sprintf('The path "%s" doesn\'t exsist.', $path));
            }

            $inputDir = realpath($inputDir);
            $outputDir = realpath($outputDir);

            Console::wl('Processing files');
            Console::wl('--------------------------------------------------------------------------');
            Console::wl('  Source path: %s', $inputDir);
            Console::wl('    Dest path: %s', $outputDir);
            Console::wl();

            // Process either directory or file
            $path = $inputDir;
            if (is_dir($inputDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path),
                    RecursiveIteratorIterator::SELF_FIRST
                );
            } else {
                $files = [new SplFileInfo($inputDir)];
            }

            $converter = new DocuwikiToMarkdownExtra();
            foreach ($files as $file) {
                $filename = $file->getFilename();

                if ($filename == '.' || $filename == '..')
                    continue;

                $inputDir = $file->getPath();
                if (is_dir($file->getPathname()))
                    continue;

                if ($outputDir) {
                    // Create output subfolder (optional)
                    $outputDir = str_replace($path, substr($path, 0, -5) . 'output', $inputDir);

                    if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);
                    $outFilename = preg_replace('/\.txt$/', '.md', $filename);
                    if ($template) {
                        $flags = FILE_APPEND;
                        Console::wl('Writing header in "%s/%s"', $outputDir, $outFilename);
                        if (file_put_contents("$outputDir/$outFilename", $template) === FALSE)
                            Console::wl('Could not write file "%s/%s"', $outputDir, $outFilename);
                    } else {
                        $flags = Constants::FILE_DEFAULTS;
                    }
                    $converter->convertFile("$inputDir/$filename", "$outputDir/$outFilename", $flags);
                } else {
                    echo $converter->convertFile("$inputDir/$filename");
                }
            }
        }
        catch (Throwable $e) {
            Console::wl('Critical: ' . $e->getMessage());
            exit(self::EXIT_FAILURE);
        }
    }

    /**
     * @param string $file
     * @return string
     * @throws FileLoadException
     */
    protected function loadContents(string $file): string
    {
        if (!file_exists($file))
            throw new FileLoadException($file);

        $contents = file_get_contents($file);
        if ($contents === false)
            throw new FileLoadException($file);

        return $contents;
    }

    /**
     * Prints the help message
     * @return void
     */
    protected function showHelp()
    {
        $lines = [
            'Converts Dokuwiki pages to Markdown',
            '',
            'Usage:',
            '  dokumd <source-path> <destination-path>',
            '',
            'Examples:',
            '  Converts all the documents inside input/ by saving the Markdown documents inside output/',
            '',
            '  dokumd input/ output/   '

        ];

        print implode("\n", $lines);
    }
}
