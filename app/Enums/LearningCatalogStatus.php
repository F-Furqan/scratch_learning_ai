<?php

namespace App\Enums;

enum LearningCatalogStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
