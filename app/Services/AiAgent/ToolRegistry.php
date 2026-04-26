<?php

declare(strict_types=1);

namespace App\Services\AiAgent;

use App\Services\AiAgent\Tools\Tool;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * @param  iterable<Tool>  $tools
     */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function definitionsForAnthropic(): array
    {
        return array_map(static fn (Tool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->inputSchema(),
        ], array_values($this->tools));
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }
}
