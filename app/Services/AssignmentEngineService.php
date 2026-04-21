<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssignmentRuleType;
use App\Enums\ConversationStatus;
use App\Models\AssignmentRule;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AssignmentEngineService
{
    /**
     * Try to auto-assign a newly created conversation.
     * Iterates active rules in priority order; first match wins.
     */
    public function autoAssign(Conversation $conversation): void
    {
        if ($conversation->assigned_to) {
            return; // Already assigned
        }

        $tenant = Tenant::find($conversation->tenant_id);
        if (! $tenant) {
            return;
        }

        $rules = AssignmentRule::withoutGlobalScope('tenant')
            ->where('tenant_id', $conversation->tenant_id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            $agent = $this->resolveAgent($rule, $conversation);
            if ($agent) {
                $conversation->update([
                    'assigned_to' => $agent->id,
                    'assigned_at' => now(),
                ]);
                Log::info('AssignmentEngine: auto-assigned', [
                    'conversation_id' => $conversation->id,
                    'agent_id'        => $agent->id,
                    'rule_id'         => $rule->id,
                    'rule_type'       => $rule->type->value,
                ]);
                return;
            }
        }
    }

    private function resolveAgent(AssignmentRule $rule, Conversation $conversation): ?User
    {
        return match ($rule->type) {
            AssignmentRuleType::RoundRobin  => $this->roundRobin($rule),
            AssignmentRuleType::LeastBusy   => $this->leastBusy($rule),
            AssignmentRuleType::TagBased    => $this->tagBased($rule, $conversation),
            AssignmentRuleType::Manual      => null, // Manual = no auto-assign
        };
    }

    // ─── Round-robin ──────────────────────────────────────────────────────────

    private function roundRobin(AssignmentRule $rule): ?User
    {
        $pool = $this->agentPool($rule);
        if ($pool->isEmpty()) {
            return null;
        }

        $cacheKey = "assignment.rr.{$rule->id}";
        $lastIdx  = (int) Cache::get($cacheKey, -1);
        $nextIdx  = ($lastIdx + 1) % $pool->count();

        Cache::put($cacheKey, $nextIdx, now()->addDays(7));

        return $pool->values()->get($nextIdx);
    }

    // ─── Least-busy ───────────────────────────────────────────────────────────

    private function leastBusy(AssignmentRule $rule): ?User
    {
        $pool = $this->agentPool($rule);
        if ($pool->isEmpty()) {
            return null;
        }

        $maxConversations = (int) ($rule->config['max_conversations'] ?? 50);

        return $pool
            ->map(function (User $agent) use ($rule, $maxConversations) {
                $count = Conversation::withoutGlobalScope('tenant')
                    ->where('tenant_id', $rule->tenant_id)
                    ->where('assigned_to', $agent->id)
                    ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
                    ->count();

                return ['agent' => $agent, 'count' => $count, 'max' => $maxConversations];
            })
            ->filter(fn ($item) => $item['count'] < $item['max'])
            ->sortBy('count')
            ->first()['agent'] ?? null;
    }

    // ─── Tag-based ────────────────────────────────────────────────────────────

    private function tagBased(AssignmentRule $rule, Conversation $conversation): ?User
    {
        $contact = Contact::withoutGlobalScope('tenant')->with('tags')->find($conversation->contact_id);
        if (! $contact) {
            return null;
        }

        $contactTagSlugs = $contact->tags->pluck('slug')->all();
        $mappings        = $rule->config['tag_mappings'] ?? []; // [ ['tag' => 'vip', 'agent_ids' => [...]] ]

        foreach ($mappings as $mapping) {
            $tag      = $mapping['tag'] ?? null;
            $agentIds = $mapping['agent_ids'] ?? [];

            if ($tag && in_array($tag, $contactTagSlugs, true) && ! empty($agentIds)) {
                $agent = User::withoutGlobalScope('tenant')
                    ->where('tenant_id', $rule->tenant_id)
                    ->where('is_active', true)
                    ->whereIn('id', $agentIds)
                    ->first();

                if ($agent) {
                    return $agent;
                }
            }
        }

        return null;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Get the agent pool for a rule (configured agent_ids or all active agents). */
    private function agentPool(AssignmentRule $rule): Collection
    {
        $agentIds = $rule->config['agent_ids'] ?? [];

        $query = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $rule->tenant_id)
            ->where('is_active', true);

        if (! empty($agentIds)) {
            $query->whereIn('id', $agentIds);
        }

        return $query->orderBy('name')->get();
    }
}
