<?php

declare(strict_types=1);

namespace App\Services\AiAgent\Tools;

use App\Models\Conversation;

interface Tool
{
    public function name(): string;

    public function description(): string;

    public function inputSchema(): array;

    public function execute(Conversation $conversation, array $input): array;
}
