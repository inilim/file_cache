<?php

declare(strict_types=1);

namespace Inilim\FileCache;

interface FilterInterface
{
    public function __invoke(\SplFileInfo $splFileInfo): bool;
}
