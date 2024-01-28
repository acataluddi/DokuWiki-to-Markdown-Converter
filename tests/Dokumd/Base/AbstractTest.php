<?php

namespace Dokumd\Base;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class AbstractTest extends TestCase
{
    /**
     * @var string|null
     */
    private ?string $samplesRootPath = null;
    /**
     * @var string|null
     */
    private ?string $testsRootPath = null;

    /**
     * @return string
     */
    protected function getTestsRootPath(): string
    {
        if ($this->testsRootPath === null) {
            $this->testsRootPath = realpath(__DIR__ . '/../../');
        }
        return $this->testsRootPath;
    }

    /**
     * @return string
     */
    protected function getSamplesRootPath(): string
    {
        if ($this->samplesRootPath === null) {
            $this->samplesRootPath = realpath($this->getTestsRootPath() . '/assets/samples');
        }
        return $this->samplesRootPath;
    }

    /**
     * @param string $path
     * @return array
     */
    protected function filesIn(string $path): array
    {
        $list = [];
        $files = scandir($path);
        if ($files === false)
            throw new RuntimeException(sprintf('Unable to scan files in "%s"', $path));

        foreach ($files as $file) {
            if (is_file("$path/$file") && $file != '.' && $file != '..' && $file != '.DS_Store')
                $list[] = "$path/$file";
        }

        return $list;
    }

    /**
     * @param Throwable $e
     * @return void
     */
    protected function failByException(Throwable $e)
    {
        $this->fail($e->getMessage());
    }
}