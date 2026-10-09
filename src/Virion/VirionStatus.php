<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

enum VirionStatus: string
{
    case LOADED = 'loaded';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
    case CONFLICT = 'conflict';
}
