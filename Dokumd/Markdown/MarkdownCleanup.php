<?php

namespace Dokumd\Markdown;

use Dokumd\Utils\Constants;

/**
 * Class MarkdownCleanup
 * Fixes certain omissions from {@link DocuwikiToMarkdownExtra}.
 * @package Dokumd\Markdown
 * @author Ingo Schommer
 */
class MarkdownCleanup
{
    /**
     * @param string $filepath
     * @return string
     */
    public function processFile(string $filepath): string
    {
        $content = $this->process(file_get_contents($filepath));
        return $this->relocateImages($content, $filepath);
    }

    /**
     * @param string $content
     * @return string
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    public function process(string $content): string
    {
        $content = $this->convertInlineHtml($content);
        $content = $this->convertUnbalancedHeadlines($content);
        $content = $this->convertCodeBlocks($content);
        $content = $this->newlinesAfterHeadlines($content);
        $content = $this->newlinesBeforeLists($content);
        $content = $this->convertApiLinks($content);
        $content = $this->convertEmphasis($content);

        return $content;
    }

    /**
     * @param string $content
     * @return string
     */
    protected function convertInlineHtml(string $content): string
    {
        $out = [];
        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $i => $line) {
            // TODO Don't convert HTML in headlines
            if (!preg_match('/^\t/', $line)) {
                $lines[$i] = preg_replace('/[*\'`]*(<[^>]*?>)[*\'`]*/', '`$1`', $line);
            }

            $out[] = $lines[$i];
        }

        return implode(Constants::LF, $out);
    }

    /**
     * Convert all image references from dokuwiki format into markdown,
     * and relocate the physical files (they were all stored in one folder regarless
     * of the markdown file location). Creates copies of images in case they're referenced
     * from multiple places, to avoid breaking already converted paths.
     *
     * Note: Doesn't add image heights from DokuWiki (e.g. image.png?100 makes it 100px wide).
     *
     * # Example
     *
     * Before: {{tutorial:home-first.png|My Title}}
     * After: ![My Title](home-first.png)
     * @param string $content
     * @param string $filepath
     * @return string
     */
    protected function relocateImages(string $content, string $filepath): string
    {
        $origImgFolder = realpath('../master/cms/docs/en/reference/_images/media/');
        $targetImgFolder = dirname($filepath) . '/images/';

        // create images folder
        if (!file_exists($targetImgFolder)) mkdir($targetImgFolder);

        preg_match_all('/\{\{\s*(.*?)\\s*}}/m', $content, $matches);
        if ($matches) foreach ($matches[1] as $i => $match) {
            // var_dump($match);
            // split into path (with optional namespaces) and optional title
            $specParts = explode('|', $match);
            $dokuwikiPath = $specParts[0];
            $title = (isset($specParts[1])) ? $specParts[1] : '';
            $title = preg_replace('/^:/', '', $title); // Remove trailing colon (root namespace)

            // Don't rewrite absolute URLs (no need to copy images either then)
            $parsed = parse_url($dokuwikiPath);
            if (isset($parsed['scheme']) && $parsed['scheme'] == 'http') {
                $targetImgHref = $dokuwikiPath;
            } else {
                $dokuwikiPath = preg_replace('/^:/', '', $dokuwikiPath); // Remove trailing colon (root namespace)
                $dokuwikiPathParts = explode(':', $dokuwikiPath);
                $filename = $dokuwikiPathParts[count($dokuwikiPathParts) - 1];
                $filename = preg_replace('/\?.*/', '', $filename); // remove querystrings from filename
                $origImgPath = $origImgFolder . '/' . implode('/', (array)$dokuwikiPathParts);
                $targetImgPath = $targetImgFolder . '/' . $filename;
                $origImgPath = preg_replace('/\?.*/', '', $origImgPath); // remove querystrings from filename

                // Unset title if it's the same as the filename
                if ($title == $filename) $title = '';

                // Copy the image file
                if (file_exists($origImgPath)) {
                    copy($origImgPath, $targetImgPath);
                } else {
                    echo sprintf('Original image not found: %s' . "\n", $origImgPath);
                }

                $targetImgHref = 'images/' . $filename;
            }

            // Change to Markdown syntax (see http://daringfireball.net/projects/markdown/syntax#img)
            $content = str_replace(
                $matches[0][$i],
                sprintf('![%s](%s)', $title, $targetImgHref),
                $content
            );
        }

        return $content;
    }

    /**
     * Convert "unbalanced" DokuWiki headlines (amount of equal signs at beginning and end not matching).
     * @param string $content
     * @return string
     */
    protected function convertUnbalancedHeadlines(string $content): string
    {
        $out = [];

        $inlineRules = array(
            '/^=([^=]*) [=\s]*/' => array("rewrite" => '###### $1'),
            '/^==([^=]*) [=\s]*/' => array("rewrite" => '##### $1'),
            '/^===([^=]*) [=\s]*/' => array("rewrite" => '#### $1'),
            '/^====([^=]*) [=\s]*/' => array("rewrite" => '### $1'),
            '/^=====([^=]*) [=\s]*/' => array("rewrite" => '## $1'),
            '/^======([^=]*) [=\s]*/' => array("rewrite" => '# $1'),
        );
        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $i => $line) {
            foreach ($inlineRules as $rule => $replace) {
                $lines[$i] = preg_replace($rule, $replace['rewrite'], $lines[$i]);
            }
            $out[] = $lines[$i];
        }

        return implode(Constants::LF, $out);
    }

    /**
     * Replace emphasis in format "//emphasized//" to "*emphasized*", but avoid replacing it
     * withing links. E.g. "[http://bla.com](http://bla.com)" shouldn't match, either should
     * "Convert http:// to https://".
     * @param string $content
     * @return string
     */
    protected function convertEmphasis(string $content):string
    {
        $out = [];

        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $i => $line) {
            // Mandate space before tags to avoid converting protocol links
            $lines[$i] = preg_replace('/\s\/\/(\S[^]]*?)\/\//', ' *$1*', $line);
            // Fix tags without space at start, but at file start
            $lines[$i] = preg_replace('/^\/\/(\S[^]]*?)\/\//', '*$1*', $lines[$i]);
            $out[] = $lines[$i];
        }

        return implode(Constants::LF, $out);
    }

    /**
     * Exchange any links to api.ss.org with a new pseudo format "[api:<classname>]".
     * Also wrap them in <pre> blocks.
     *
     * Excludes composite structures with spaces etc., we can't be sure they're class names.
     * @param string $content
     * @return string
     */
    protected function convertApiLinks(string $content):string
    {
        $out = [];

        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $line) {
            preg_replace('/\[(\w*)]\(http:\/\/api\.silverstripe.org.*\)/', '`[api:$1]`', $line);
            $out[] = $line;
        }

        return implode(Constants::LF, $out);
    }

    /**
     * Headlines should be followed by newlines in markdown, for easier readability.
     * @param string $content
     * @return string
     */
    protected function newlinesBeforeLists(string $content):string
    {
        $out = [];
        $re = '/^[\s\t]*\*/';

        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $i => $line) {
            if (preg_match($re, $line) && isset($lines[$i - 1]) && !empty($lines[$i - 1]) && !preg_match($re, $lines[$i - 1])) {
                $lines[$i] = Constants::LF . $line;
            }

            $out[] = $lines[$i];
        }

        return implode(Constants::LF, $out);
    }

    /**
     * Headlines should be followed by newlines in markdown, for easier readability.
     * @param string $content
     * @return string
     */
    protected function newlinesAfterHeadlines(string $content):string
    {
        $out = [];

        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $i => $line) {
            if (preg_match('/^#/', $line) && isset($lines[$i + 1]) && !empty($lines[$i + 1])) {
                $lines[$i + 1] = Constants::LF . $lines[$i + 1];
            }

            $out[] = $lines[$i];
        }

        return implode(Constants::LF, $out);
    }

    /**
     * Convert Markdown Extra style code blocks with triple tildes
     * into more standard tab-indented code blocks with CodeHilite convetions.
     * @param string $content
     * @return string
     */
    protected function convertCodeBlocks(string $content): string
    {
        $out = [];
        $linemode = 'text'; // 'text' or 'code'
        $extraPreNewline = false;

        $lines = DocuwikiToMarkdownExtra::getLines($content);
        foreach ($lines as $i => $line) {
            if (preg_match('/^~~~(\s{(.*)})?/', $line, $matches)) {
                // first line of code block
                if ($linemode == 'text') {
                    $linemode = 'code';

                    // add code formatting bit
                    $lines[$i] = (isset($matches[2])) ? "```" . $matches[2] : '```';

                    // if previous line is not empty, add a newline
                    $extraPreNewline = (isset($lines[$i - 1]) && !empty($lines[$i - 1]));
                } else {
                    // last line of code block
                    $linemode = 'text';
                    $lines[$i] = '```'; // The closing code block
                }
            }

            if ($extraPreNewline) $lines[$i] = Constants::LF . $lines[$i];
            $extraPreNewline = false;

            $out[] = $lines[$i];
        }

        return implode(Constants::LF, $out);
    }
}