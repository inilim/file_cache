<?php

declare(strict_types=1);

namespace Inilim\FileCache;

use Inilim\FileCache\FilterInterface;

final class FilterRegexByPath implements FilterInterface
{
    protected string $pattern;

    function __construct(
        string $pattern
    ) {
        $this->pattern = $pattern;
    }

    function __invoke(\SplFileInfo $splFileInfo): bool
    {
        return (bool)\preg_match($this->pattern, $splFileInfo->getPathname());
    }
}
