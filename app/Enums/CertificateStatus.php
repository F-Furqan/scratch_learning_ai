<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
