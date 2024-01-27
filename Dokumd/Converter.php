<?php

namespace Dokumd;
/**
 * php Converter.php <inputfile|inputdir> <outputdir>
 *
 * If no <outputdir> is given, writes to stdout.
 *
 * Converts a file or folder (incl. subdirs) to markdown,
 * and writes files to a new output location.
 *
 * @author Mark Stephens, Ingo Schommer
 */

use Dokumd\Markdown\DocuwikiToMarkdownExtra;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Converter
{
    /**
     * The script entrypoint
     * @return void
     */
    public static function run()
    {
        global $argv;
        $inputDir = (isset($argv[1])) ?
            realpath($argv[1]) :
            realpath(__DIR__ . '/../input');
        $outputDir = (isset($argv[2])) ?
            realpath($argv[2]) :
            realpath(__DIR__ . '/../output');

        echo "Output Path ", $outputDir, "\n";
        $template = (isset($argv[3])) ? file_get_contents(realpath($argv[3])) : false;


        $converter = new DocuwikiToMarkdownExtra();
        $path = realpath($inputDir);

        // Process either directory or file
        if (is_dir($inputDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } else {
            $files =[new SplFileInfo($inputDir)];
        }


        foreach ($files as $file) {
            $filename = $file->getFilename();

            if ($filename == "." || $filename == "..")
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
                    echo "Writing file ", "{$outputDir}/{$outFilename}", "\n";
                    if (file_put_contents("{$outputDir}/{$outFilename}", $template) === FALSE)
                        echo "Could not write file {$outputDir}/{$outFilename}\n";
                } else {
                    $flags = 0;
                }
                $converter->convertFile("{$inputDir}/{$filename}", "{$outputDir}/{$outFilename}", $flags);
            } else {
                echo $converter->convertFile("{$inputDir}/{$filename}");
            }
        }
    }
}
