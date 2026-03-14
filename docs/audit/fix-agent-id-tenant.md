## Automation agent tenant fix

- Updated `app/Http/Requests/Api/AutomationRequest.php` to validate `action_config` conditionally by `action_type`.
- `action_config.message` is now required for `send_message`.
- `action_config.agent_id` is now required for `assign_agent` and must match an active user in the authenticated user's tenant.
- `action_config.stage` is now required for `move_stage` and must be a valid `DealStage` enum value.
- Updated `app/Services/AutomationEngineService.php` so `assign_agent` throws a `LogicException` if the automation tenant does not match the conversation tenant.
- Updated the same service to resolve the assignee through a tenant-scoped active `User` query before writing `conversations.assigned_to`.
