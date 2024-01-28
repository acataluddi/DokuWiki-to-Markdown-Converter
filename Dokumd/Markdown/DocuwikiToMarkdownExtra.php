<?php

namespace Dokumd\Markdown;

use Dokumd\Utils\Console;
use Dokumd\Utils\Constants;

/**
 * Class DocuwikiToMarkdownExtra
 * Convert docuwiki syntax to markdown.
 *
 * KNOWN BUGS:
 *  - inline code snippets have other inline transforms applied to the code
 *    body. It needs to be multi-pass:
 *      - find inline code and replace with a non-translating unique identifier
 *      - apply other transforms
 *      - replace unique identifiers with new markup.
 *
 * @package Dokumd\Markdown
 * @author Mark Stephens
 */
class DocuwikiToMarkdownExtra
{
    /**
     * These rules are applied whereever inline styling is permitted
     * @var array
     */
    static array $inlineRules = [
        // Headings
        '/^= (.*) =$/' => ["rewrite" => '###### \1'],
        '/^=([^=]*)=*$/' => ["rewrite" => '###### \1'],
        '/^== (.*) ==$/' => ["rewrite" => '##### \1'],
        '/^==([^=]*)=*$/' => ["rewrite" => '##### \1'],
        '/^=== (.*) ===$/' => ["rewrite" => '#### \1'],
        '/^===([^=]*)=*$/' => ["rewrite" => '#### \1'],
        '/^==== (.*) ====$/' => ["rewrite" => '### \1'],
        '/^====([^=]*)=*$/' => ["rewrite" => '### \1'],
        '/^===== (.*) =====$/' => ["rewrite" => '## \1'],
        '/^=====([^=]*)=*$/' => ["rewrite" => '## \1'],
        '/^====== (.*) ======$/' => ["rewrite" => '# \1'],
        '/^======([^=]*)=*$/' => ["rewrite" => '# \1'],

        // Link syntaxes, most specific first
        '/\[\[.*?\|\{\{.*?\}\}\]\]/U' => ["notice" => "Link with image seen, not handled properly"],
        '/\[\[.*?\#.*?\|.*?\]\]/U' => ["notice" => "Link with segment seen, not handled properly"],
        '/\[\[.*?\>.*?\]\]/U' => ["notice" => "interwiki syntax seen, not handled properly"],
        '/\[\[(.*)\]\]/U' => ["call" => "handleLink"],

        // Inline code.
        '/\<code\>(.*)\<\/code\>/U' => ["rewrite" => '`\1`'],
        /** @lang text */
        '/\<code (.*)\>(.*)\<\/code\>/U' => ["rewrite" => '`\2`{\1}'],

        // Misc checks
        '/^\d*\.\s/' => ["notice" => "Possible numbered list item that is not docuwiki format, not handled"],
        '/^=+\s*.*$/' => ["notice" => "Line starts with an =. Possibly an untranslated heading. Check for = in the heading text"],
    ];
    /**
     * Contains the name of current input file being processed.
     * @var string
     */
    protected string $fileName;
    /**
     * Contains the line number of the input file currently being processed.
     * @var int
     */
    protected int $lineNumber;


    /**
     * Used when parsing lists. Has one of three values:
     *   ""           not processing a list.
     *   "unordered"  processing items in an unordered list
     *   "ordered"    processing items in an ordered list
     * @var string
     */
    protected string $listItemType;
    /**
     * Counter for ordered lists.
     * @var int
     */
    protected int $listItemCount;
    /**
     * @var string
     */
    protected static string $underline = "";

    /**
     * Converts a docuwiki file in the input directory and called $filename, and re-created it in the output directory,
     * translated to markdown extra.
     * @param string $inputFile
     * @param string|null $outputFile
     * @param int $flags
     * @return string
     */
    public function convertFile(string $inputFile, ?string $outputFile = null, int $flags = Constants::FILE_DEFAULTS): string
    {
        Console::wl(' [Converting] "%s"', $inputFile);
        $this->fileName = $inputFile;
        $contents = file_get_contents($inputFile);
        $contents = $this->convert($contents);

        if ($outputFile) {
            if (file_put_contents($outputFile, $contents, $flags) === FALSE)
                echo "Could not write file $outputFile\n";
        }

        return $contents;
    }

    /**
     * Converts the given text buffer
     * @param string $contents
     * @return string
     */
    public function convert(string $contents): string
    {
        $lines = self::getLines($contents);

        $output = "";
        $lineMode = "text";

        $this->listItemType = "";
        $this->lineNumber = 0;
        $table = [];

        foreach ($lines as $line) {
            $line = $this->tabsToSpace($line);
            $this->lineNumber++;

            $prevLineMode = $lineMode;

            // Determine if the line mode is changing
            $tl = trim($line);

            if ($lineMode != "code" && preg_match('/^<code(|\s([a-zA-Z0-9])*)>$/U', $tl)) {
                $line = "~~~";
                if ($tl != "<code>") $line .= " {" . substr($tl, 6, -1) . "}";
                $lineMode = "code";
            } else if ($lineMode == "code" && $tl == "</code>") {
                $line = "~~~";
                $lineMode = "text";
            } else if ($lineMode == "text" && strlen($tl) > 0 &&
                ($tl[0] == "^" || $tl[0] == "|")) {
                // first char is a ^ so it's the start of a table. In table mode we
                // just accumulate table rows in $table, and render when
                // we switch out of table mode, so we can do column widths right.
                $lineMode = "table";
                $table = [];
            } else if ($lineMode == "table" && ($tl == "" ||
                    ($tl[0] != "^" && $tl[0] != "|"))) {
                $lineMode = "text";
            }

            if ($prevLineMode == "table" && $lineMode != "table") {
                $output .= $this->renderTable($table);
            }

            // perform mode-specific translations
            switch ($lineMode) {
                case "text":
                    $line = $this->convertInlineMarkup($line);
                    $line = $this->convertListItems($line);
                    break;
                case "code":
                    break;
                case "table":
                    // Grab this line, break it up and add it to $table after
                    // performing inline transforms on each cell.
                    $parts = explode($tl[0], $this->convertInlineMarkup($line));
                    for ($i = 0; $i < count($parts); $i++)
                        $parts[$i] = trim($parts[$i]);
                    $table[] = $parts;
                    break;
            }

            if ($lineMode != "table") $output .= $line . "\n";
        }

        return (new MarkdownCleanup())->process($output);
    }

    /**
     * Return an array of lines from s, ensuring we handle different end-of-line
     * variations
     * @param string $s
     * @return string[]
     */
    public static function getLines(string $s): array
    {
        // Ensure that we only have a single \n at the end of each line.
        $s = str_replace(Constants::CRLF, Constants::LF, $s);
        $s = str_replace(Constants::CR, Constants::LF, $s);

        return explode(Constants::LF, $s);
    }

    /**
     * @param array $table
     * @return string
     */
    protected function renderTable(array $table): string
    {
        // get a very big underline
        if (!self::$underline) for ($i = 0; $i < 100; $i++) self::$underline .= "----------";

        // Calculate maximum columns widths
        $widths = [];
        foreach ($table as $row) {
            for ($i = 0; $i < count($row); $i++) {
                if (!isset($widths[$i])) $widths[$i] = 0;
                if (strlen($row[$i]) > $widths[$i]) $widths[$i] = strlen($row[$i]);
            }
        }

        $s = "";
        $headingRow = true;
        foreach ($table as $row) {
            for ($i = 0; $i < count($row); $i++) {
                if ($i > 0) $s .= " | ";
                $s .= str_pad($row[$i], $widths[$i]);
            }
            $s .= "\n";

            if ($headingRow) {
                // underlines of the length of the column headings
                for ($i = 0; $i < count($row); $i++) {
                    if ($i > 0) $s .= " | ";
                    $s .= str_pad(substr(self::$underline, 0, strlen($row[$i])), $widths[$i]);
                }
                $s .= "\n";
            }

            $headingRow = false;
        }

        return $s;
    }

    /**
     * Perform inline translations.
     * @param string $line
     * @return mixed
     */
    protected function convertInlineMarkup(string $line)
    {
        // Apply regexp rules
        foreach (self::$inlineRules as $from => $to) {
            if (isset($to["rewrite"]))
                $line = preg_replace($from, $to["rewrite"], $line);
            if (isset($to["notice"]) && preg_match($from, $line))
                $this->notice($to["notice"]);
            if (isset($to["call"]) && preg_match_all($from, $line, $matches))
                $line = call_user_func_array(array($this, $to["call"]), array($line, $matches));
        }

        return $line;
    }

    /**
     * Handle transforming list items:
     * __* text        [unordered list item] =>
     * __-            [ordered list item] =>
     * Doesn't handle nested lists, but will emit a notice.
     * @param string $s
     * @return string
     */
    protected function convertListItems(string $s): string
    {
        if ($s == "") return $s;

        if (substr($s, 0, 2) != "  " && $s[0] != "\t" && trim($s) != "") {
            // Termination condition for a list is that the text is not
            // indented.
            $this->listItemType = "";
            return $s;
        }

        if (substr($s, 0, 3) == "  *") {
            $this->listItemType = "unordered";
            $s = substr($s, 2); // remove leading space

            // force exactly 2 spaces after bullet to make things line up nicely.
            if (substr($s, 1, 1) != " ") $s = "\n* " . substr($s, 1);
            if (substr($s, 2, 1) != " ") $s = "\n*  " . substr($s, 2);
        } else if (substr($s, 0, 3) == "  -") {
            if ($this->listItemType != "ordered") $this->listItemCount = 1;
            $this->listItemType = "ordered";
            $s = " " . $this->listItemCount . ". " . substr($s, 3);
            $this->listItemCount++;
        } else if (substr($s, 0, 3) == "   ") {
            $t = trim($s);
            if ($t && ($t[0] == "*" || $t[0] == "-"))
                $this->notice("Possible nested indent, which isn't handled");
        } else if (substr($s, 0, 2) == "  ") {
            // we're a list, but this line is not the start of a point, so
            // indent it. We indent by 4 spaces, which is required for additional
            // paragraphs in an item in markdown. We only have to add 2, because
            // there are already 2 there.
            $s = "  " . $s;
        }

        return $s;
    }

    /**
     * Called by a rule that match links with [[ ]]. $line is the line to munge.
     * $matchArgs are passed from preg_match_all; there are always two entries
     * and $matchArgs[0] is the source link including [[ and ]], and is what
     * we can replace with a link.
     *
     * some variants:
     * - [[http://doc.silverstripe.org/doku.php?id=contributing#reporting_security_issues|contributing guidelines]]
     * has a # fragment, but part of the URL, not ahead of the URL as specified in docuwiki
     * - [[http://url|{{http://url/file.png}}]] (x1)
     * - [[recipes:forms]]
     * - [[recipes:forms|our form recipes]]
     * - [[tutorial:3-forms#showing_the_poll_results|tutorial:3-forms: Showing the poll results]]
     * - [[directory-structure#module_structure|directory structure guidelines]]
     * - [[:themes:developing]]
     * - [[GSoc:2007:i18n]]
     * - [[:Requirements]]
     * - [[#ComponentSet]]
     * - [[ModelAdmin#searchable_fields]]
     * - [[irc:our weekly core discussions on IRC]]
     * - [[#documentation|documentation]]
     * - [[community run third party websites]]
     * - [[requirements#including_inside_template_files|Includes in Templates]]
     *
     * @param string $line
     * @param array $matchArgs
     * @return string
     * @noinspection PhpUnused
     */
    protected function handleLink(string $line, array $matchArgs): string
    {
        foreach ($matchArgs[0] as $match) {
            $link = substr($match, 2, -2);
            $parts = explode("|", $link);

            if (count($parts) == 1) $replacement = "[" . $parts[0] . "](" . $this->translateInternalLink($parts[0]) . ")";
            else {
                if (strpos($parts[1], "{{")) $this->notice("Image inside link not translated, requires manual editing");
                $replacement = "[" . $parts[1] . "](" . $this->translateInternalLink($parts[0]) . ")";
            }

            $line = str_replace($match, $replacement, $line);
        }

        return $line;
    }

    /**
     * Called by rules that match image references with {{ }}
     * Specific cases that we handle are:
     * - {{:file.png|:file.png}}
     * - {{http://something/file.png}}
     * - {{http://something/file.png|display}}
     * - {{tutorial:file.png}}
     * @param string $line
     * @param array $matchArgs
     * @noinspection PhpUnused
     */
    protected function handleImage(string $line, array $matchArgs)
    {
        foreach ($matchArgs[0] as $match) {
            $link = substr($match, 2, -2);
            $parts = explode("|", $link);

            if (count($parts) == 1) $replacement = "![" . $parts[0] . "](" . $this->translateInternalLink($parts[0]) . ")";
            else $replacement = "![" . $parts[1] . "](" . $this->translateInternalLink($parts[0]) . ")";

            $line = str_replace($match, $replacement, $line);
        }

        // Note: the original implementation seems to be incomplete.
    }

    /**
     * Convert an internal docuwiki link, which is basically some combination
     * of identifiers with ":" separators ("namespaces"), which are really
     * folders. The input is any link. This only alters internal links.
     *
     * @param string $s
     * @return string
     */
    protected function translateInternalLink(string $s): string
    {
        if (substr($s, 0, 5) == "http:" || substr($s, 0, 6) == "https") return $s;
        return str_replace(":", "/", $s);
    }

    /**
     * Service method to print a notice.
     * @param string $message
     * @return void
     */
    protected function notice(string $message)
    {
        Console::wl('      Notice: %s:%s. %s', basename($this->fileName), $this->lineNumber, $message);
    }

    /**
     * @param string $line
     * @return string
     */
    protected function tabsToSpace(string $line): string
    {
        return str_replace("\t", '    ', $line);
    }
}
