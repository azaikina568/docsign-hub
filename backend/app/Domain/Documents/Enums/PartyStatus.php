<?php

namespace App\Domain\Documents\Enums;

enum PartyStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
}
