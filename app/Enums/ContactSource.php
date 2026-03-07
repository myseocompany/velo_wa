<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactSource: string
{
    case WhatsApp = 'whatsapp';
    case Manual = 'manual';
    case Import = 'import';
}
