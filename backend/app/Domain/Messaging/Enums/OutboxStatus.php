<?php

namespace App\Domain\Messaging\Enums;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';
}
