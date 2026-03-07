<?php

declare(strict_types=1);

namespace App\Enums;

enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Manual = 'manual';
}
