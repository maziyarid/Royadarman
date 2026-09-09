<?php

namespace App\Domain\Support\Enums;

enum SupportCategory: string
{
    case General = 'general';
    case Technical = 'technical';
    case Coordination = 'coordination';
    case ClinicalQuestion = 'clinical_question';
    case Referral = 'referral';
    case Billing = 'billing';
}
