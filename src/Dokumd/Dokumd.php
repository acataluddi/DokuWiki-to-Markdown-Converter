<?php

namespace Dokumd;

use Dokumd\Markdown\DocuwikiToMarkdownExtra;
use Dokumd\Markdown\MarkdownDetector;
use Dokumd\Utils\Console;
use Dokumd\Utils\Constants;
use Dokumd\Utils\FileUtils;
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
     * Returns the tool version.
     * @return string
     */
    public static function getVersion(): string
    {
        return DOKUMD_VERSION;
    }

    /**
     * returns the tool name.
     * @return string
     */
    public static function getName(): string
    {
        return 'Dockumd';
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
            $fileHeader = (isset($argv[3])) ? FileUtils::load($argv[3]) : false;

            foreach ([$inputDir, $outputDir] as $path) {
                if (!is_dir($path))
                    throw new RuntimeException(sprintf('The path "%s" doesn\'t exsist.', $path));
            }

            $inputDir = realpath($inputDir);
            $outputDir = realpath($outputDir);

            Console::wl( '%s (version %s)', self::getName(), self::getVersion());
            Console::wl();

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

            $convertedExt = array_flip(['md', 'txt']);
            $copied = $converted = 0;
            $detector = new MarkdownDetector();
            foreach ($files as $file) {
                $filename = $file->getFilename();

                if ($filename == '.' || $filename == '..')
                    continue;

                $inputDir = $file->getPath();
                if (is_dir($file->getPathname()))
                    continue;

                // Create output subfolder
                $outputDir = str_replace($path, substr($path, 0, -5) . 'output', $inputDir);

                if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);
                $outFilename = preg_replace('/\.txt$/', '.md', $filename);

                // Only .md and .txt files are converted. The others are just copied as they are
                if (!array_key_exists(strtolower($file->getExtension()), $convertedExt)) {
                    Console::wl('    [Copying] "%s"', $file);
                    FileUtils::copy("$inputDir/$filename", "$outputDir/$outFilename");
                    $copied++;
                    continue;
                }

                if ($detector->containsMarkdown($file)) {
                    Console::wl('  [! Copying] "%s" (Markdown detected)', $file);
                    FileUtils::copy("$inputDir/$filename", "$outputDir/$outFilename");
                    $copied++;
                    continue;
                }

                if ($fileHeader) {
                    $flags = FILE_APPEND;
                    Console::wl('Writing header in "%s/%s"', $outputDir, $outFilename);
                    if (file_put_contents("$outputDir/$outFilename", $fileHeader) === FALSE)
                        Console::wl('Could not write file "%s/%s"', $outputDir, $outFilename);
                } else {
                    $flags = Constants::FILE_DEFAULTS;
                }

                $converter->convertFile("$inputDir/$filename", "$outputDir/$outFilename", $flags);
                $converted++;
            }

            Console::wl('');
            Console::wl('Completed. Converted: %s Copied: %s Total: %s', $converted, $copied, $converted + $copied);
        }
        catch (Throwable $e) {
            Console::wl('Critical: ' . $e->getMessage());
            exit(self::EXIT_FAILURE);
        }
    }

    /**
     * Prints the help message
     * @return void
     */
    protected function showHelp()
    {
        $lines = [
            sprintf('%s (version %s)', self::getName(), self::getVersion()),
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
