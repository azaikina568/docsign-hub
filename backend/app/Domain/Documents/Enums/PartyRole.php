<?php

namespace App\Domain\Documents\Enums;

enum PartyRole: string
{
    case Signer = 'signer';
    case Viewer = 'viewer';
}
