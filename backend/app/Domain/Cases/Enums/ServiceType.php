<?php

namespace App\Domain\Cases\Enums;

enum ServiceType: string
{
    case GuidanceReferral = 'guidance_referral';
    case HomeDentistry = 'home_dentistry';
    case OpgReview = 'opg_review';
}
