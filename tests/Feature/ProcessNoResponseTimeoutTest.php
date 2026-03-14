<?php

declare(strict_types=1);

use App\Enums\AutomationActionType;
use App\Enums\AutomationTriggerType;
use App\Enums\ConversationStatus;
use App\Jobs\ProcessNoResponseTimeout;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\AutomationEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('does not re-run completed timeout automations while processing another eligible automation', function () {
    \Carbon\Carbon::setTestNow('2026-03-14 10:00:00');

    [$tenant, $conversation] = timeoutConversationFixture();

    $completedAutomation = Automation::create([
        'tenant_id'      => $tenant->id,
        'name'           => 'First follow-up',
        'trigger_type'   => AutomationTriggerType::NoResponseTimeout,
        'trigger_config' => ['minutes' => 30],
        'action_type'    => AutomationActionType::SendMessage,
        'action_config'  => ['message' => 'Already sent'],
        'is_active'      => true,
        'priority'       => 10,
    ]);

    $pendingAutomation = Automation::create([
        'tenant_id'      => $tenant->id,
        'name'           => 'Second follow-up',
        'trigger_type'   => AutomationTriggerType::NoResponseTimeout,
        'trigger_config' => ['minutes' => 30],
        'action_type'    => AutomationActionType::SendMessage,
        'action_config'  => ['message' => 'Send this one'],
        'is_active'      => true,
        'priority'       => 20,
    ]);

    AutomationLog::create([
        'tenant_id'       => $tenant->id,
        'automation_id'   => $completedAutomation->id,
        'conversation_id' => $conversation->id,
        'trigger_type'    => AutomationTriggerType::NoResponseTimeout->value,
        'action_type'     => AutomationActionType::SendMessage->value,
        'status'          => 'success',
        'triggered_at'    => now()->subMinute(),
    ]);

    (new ProcessNoResponseTimeout)->handle(app(AutomationEngineService::class));

    expect(Message::query()->pluck('body')->all())->toBe(['Send this one']);
    expect(
        AutomationLog::query()
            ->where('automation_id', $completedAutomation->id)
            ->where('status', 'success')
            ->count()
    )->toBe(1);
    expect(
        AutomationLog::query()
            ->where('automation_id', $pendingAutomation->id)
            ->where('status', 'success')
            ->count()
    )->toBe(1);

    Bus::assertDispatched(SendWhatsAppMessage::class, 1);
});

it('skips a timeout automation when a success log already exists for the conversation', function () {
    \Carbon\Carbon::setTestNow('2026-03-14 10:00:00');

    [$tenant, $conversation] = timeoutConversationFixture();

    $automation = Automation::create([
        'tenant_id'      => $tenant->id,
        'name'           => 'Single follow-up',
        'trigger_type'   => AutomationTriggerType::NoResponseTimeout,
        'trigger_config' => ['minutes' => 30],
        'action_type'    => AutomationActionType::SendMessage,
        'action_config'  => ['message' => 'Should not resend'],
        'is_active'      => true,
        'priority'       => 10,
    ]);

    AutomationLog::create([
        'tenant_id'       => $tenant->id,
        'automation_id'   => $automation->id,
        'conversation_id' => $conversation->id,
        'trigger_type'    => AutomationTriggerType::NoResponseTimeout->value,
        'action_type'     => AutomationActionType::SendMessage->value,
        'status'          => 'success',
        'triggered_at'    => now()->subMinute(),
    ]);

    app(AutomationEngineService::class)->dispatchAutomation(
        $automation,
        $conversation,
        AutomationTriggerType::NoResponseTimeout,
    );

    expect(Message::count())->toBe(0);
    expect(AutomationLog::count())->toBe(1);

    Bus::assertNothingDispatched();
});

function timeoutConversationFixture(): array
{
    $tenant = Tenant::create([
        'name' => 'Acme',
        'slug' => 'acme',
    ]);

    $contact = Contact::create([
        'tenant_id' => $tenant->id,
        'phone'     => '573001112233',
        'name'      => 'Jane Doe',
    ]);

    $conversation = Conversation::create([
        'tenant_id'        => $tenant->id,
        'contact_id'       => $contact->id,
        'status'           => ConversationStatus::Open,
        'first_message_at' => now()->subMinutes(31),
        'last_message_at'  => now()->subMinutes(31),
        'message_count'    => 1,
    ]);

    return [$tenant, $conversation];
}
