<?php

namespace Dokumd\Markdown;

use Dokumd\Base\AbstractTest;
use Dokumd\Exceptions\FileLoadException;

class MarkdownDetectorTest extends AbstractTest
{
    public function testContainsMarkdown()
    {
        try {
            $files = $this->filesIn($this->getSamplesPath());
            $detector = new MarkdownDetector();
            foreach ($files as $file) {
                strpos(basename($file), 'markdown') === 0 ?
                    $expectedMarkdown = true :
                    $expectedMarkdown = false;

                $expectedMarkdown ?
                    $expectation = 'expected to contain' :
                    $expectation = 'expected NOT to contain';

                $this->assertEquals($expectedMarkdown, $detector->fileContainsMarkdown($file),
                    sprintf('File "%s" %s Markdown code.', $file, $expectation)
                );
            }

            $this->assertTrue(true);
        }
        catch (FileLoadException $e) {
            $this->failByException($e);
        }
    }

    /**
     * @return string
     */
    protected function getSamplesPath(): string
    {
        return $this->getSamplesRootPath() . '/Dokumd/Markdown/MarkdownDetector';
    }
}
