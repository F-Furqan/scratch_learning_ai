<?php

namespace App\Enums;

enum TeamSeatStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Revoked = 'revoked';
}
