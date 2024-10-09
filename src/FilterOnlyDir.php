<?php

declare(strict_types=1);

namespace Inilim\FileCache;

use Inilim\FileCache\FilterInterface;

final class FilterOnlyDir implements FilterInterface
{
    function __invoke(\SplFileInfo $splFileInfo): bool
    {
        return $splFileInfo->isDir();
    }
}
