<?php

declare(strict_types=1);

namespace Inilim\FileCache;

use Inilim\FileCache\FilterInterface;

final class FilterLenFileName implements FilterInterface
{
    protected int $len;

    function __construct(
        int $len
    ) {
        $this->len = $len;
    }

    function __invoke(\SplFileInfo $splFileInfo): bool
    {
        return \strlen($splFileInfo->getBasename()) === $this->len;
    }
}
