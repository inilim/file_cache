<?php

declare(strict_types=1);

namespace Inilim\FileCache;

use Inilim\FileCache\FilterInterface;

final class FilterRegexByPath implements FilterInterface
{
    protected string $pattern;
    protected bool $invertMatch;

    function __construct(
        string $pattern,
        bool $invertMatch = false
    ) {
        $this->pattern     = $pattern;
        $this->invertMatch = $invertMatch;
    }

    function __invoke(\SplFileInfo $splFileInfo): bool
    {
        $res = (bool)\preg_match($this->pattern, $splFileInfo->getPathname());
        if ($this->invertMatch) {
            return !$res;
        }
        return $res;
    }
}
