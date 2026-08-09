<?php

namespace App\Enums;

enum CourseEnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';
    case Expired = 'expired';
}
