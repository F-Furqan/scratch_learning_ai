<?php

namespace App\Enums;

enum VideoType: string
{
    case None = 'none';
    case Url = 'url';
    case Upload = 'upload';
}
