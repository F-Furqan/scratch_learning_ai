<?php

namespace App\Enums;

enum DripReleaseType: string
{
    case Immediate = 'immediate';
    case DaysAfterEnrollment = 'days_after_enrollment';
    case SpecificDate = 'specific_date';
}
