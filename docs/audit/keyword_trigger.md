## Summary

The current keyword-trigger implementation is internally aligned on `trigger_config.case_sensitive` in the inspected code path, not `case_insensitive`. The practical mismatch is that the contract is not normalized anywhere: the UI text is phrased as "Ignorar mayusculas" (a `case_insensitive` concept), while the backend only validates and evaluates `case_sensitive`. As a result, any client, stale frontend build, or persisted `trigger_config` payload that sends `case_insensitive` will not be honored. The controller also persists `trigger_config` verbatim, and the model only casts it to `array`, so there is no compatibility layer at write or read time.

Recommended unified contract: standardize on `trigger_config.case_insensitive` across UI, validation, storage, API resource output, and engine evaluation. That matches the user-facing checkbox semantics and removes the current double-negative inversion in the frontend.

## Issues Found (with file:line references)

1. UI semantics are "ignore case", but the payload key is the inverse boolean `case_sensitive`, which makes the contract harder to reason about and easy to mismatch with other clients.
   - [resources/js/Pages/Settings/Automations.tsx](/Users/projects/velo_wa/resources/js/Pages/Settings/Automations.tsx#L27) defines `TriggerConfig.case_sensitive`.
   - [resources/js/Pages/Settings/Automations.tsx](/Users/projects/velo_wa/resources/js/Pages/Settings/Automations.tsx#L263) initializes local state from `trigger_config.case_sensitive`.
   - [resources/js/Pages/Settings/Automations.tsx](/Users/projects/velo_wa/resources/js/Pages/Settings/Automations.tsx#L286) sends `case_sensitive` in the API payload.
   - [resources/js/Pages/Settings/Automations.tsx](/Users/projects/velo_wa/resources/js/Pages/Settings/Automations.tsx#L465) binds the checkbox as `checked={!caseSensitive}`, proving the user-facing control is really `case_insensitive`.

2. Backend validation only accepts `trigger_config.case_sensitive`, so a client that sends `case_insensitive` is rejected before persistence.
   - [app/Http/Controllers/Api/V1/AutomationController.php](/Users/projects/velo_wa/app/Http/Controllers/Api/V1/AutomationController.php#L25) and [app/Http/Controllers/Api/V1/AutomationController.php](/Users/projects/velo_wa/app/Http/Controllers/Api/V1/AutomationController.php#L41) rely entirely on `AutomationRequest` for validation and then store `trigger_config` as-is.
   - [app/Http/Requests/Api/AutomationRequest.php](/Users/projects/velo_wa/app/Http/Requests/Api/AutomationRequest.php#L24) only validates `trigger_config.case_sensitive`; there is no rule for `trigger_config.case_insensitive`.

3. The engine only reads `case_sensitive`, so legacy or external payloads stored with `case_insensitive` silently fall back to the default behavior.
   - [app/Services/AutomationEngineService.php](/Users/projects/velo_wa/app/Services/AutomationEngineService.php#L119) loads raw `trigger_config`.
   - [app/Services/AutomationEngineService.php](/Users/projects/velo_wa/app/Services/AutomationEngineService.php#L122) reads only `$config['case_sensitive'] ?? false`.
   - [app/Services/AutomationEngineService.php](/Users/projects/velo_wa/app/Services/AutomationEngineService.php#L128) applies lowercase matching only when `case_sensitive` is false, so an unrecognized `case_insensitive` key is ignored.

4. The model layer does not normalize the contract, so the database can hold whichever key a caller submits.
   - [app/Models/Automation.php](/Users/projects/velo_wa/app/Models/Automation.php#L19) allows mass assignment of `trigger_config`.
   - [app/Models/Automation.php](/Users/projects/velo_wa/app/Models/Automation.php#L31) only casts `trigger_config` to `array`; it does not map `case_sensitive` and `case_insensitive` to a single canonical field.
   - [app/Http/Resources/AutomationResource.php](/Users/projects/velo_wa/app/Http/Resources/AutomationResource.php#L18) returns `trigger_config` unchanged, so mixed historical keys would leak back to clients.

## Proposed Fix (code snippets where applicable)

Adopt one canonical field: `trigger_config.case_insensitive`.

1. UI: remove the inverted boolean and send the canonical field directly.

```tsx
interface TriggerConfig {
    keywords?: string[];
    match_type?: 'any' | 'all';
    case_insensitive?: boolean;
    minutes?: number;
}

const [caseInsensitive, setCaseInsensitive] = useState(
    automation?.trigger_config?.case_insensitive ?? true,
);

function buildTriggerConfig(): TriggerConfig {
    switch (triggerType) {
        case 'keyword':
            return {
                keywords: keywords.split(',').map((k) => k.trim()).filter(Boolean),
                match_type: matchType,
                case_insensitive: caseInsensitive,
            };
        default:
            return {};
    }
}
```

2. Validation: accept only the canonical field after the UI is updated. If a compatibility window is needed, temporarily accept both and normalize in `prepareForValidation()`.

```php
protected function prepareForValidation(): void
{
    $triggerConfig = $this->input('trigger_config', []);

    if (array_key_exists('case_sensitive', $triggerConfig) && ! array_key_exists('case_insensitive', $triggerConfig)) {
        $triggerConfig['case_insensitive'] = ! (bool) $triggerConfig['case_sensitive'];
        unset($triggerConfig['case_sensitive']);
    }

    $this->merge(['trigger_config' => $triggerConfig]);
}

public function rules(): array
{
    return [
        'trigger_config.case_insensitive' => ['nullable', 'boolean'],
    ];
}
```

3. Engine: check only `case_insensitive` after request normalization.

```php
$caseInsensitive = (bool) ($config['case_insensitive'] ?? true);
$body = $caseInsensitive ? mb_strtolower($message->body) : $message->body;

$matches = array_filter($keywords, function (string $kw) use ($body, $caseInsensitive): bool {
    $needle = $caseInsensitive ? mb_strtolower($kw) : $kw;
    return str_contains($body, $needle);
});
```

4. Model/resource: normalize output so API consumers always receive the canonical key.

```php
protected function triggerConfig(): Attribute
{
    return Attribute::make(
        get: function ($value) {
            $config = is_array($value) ? $value : (json_decode($value ?? '[]', true) ?: []);

            if (array_key_exists('case_sensitive', $config) && ! array_key_exists('case_insensitive', $config)) {
                $config['case_insensitive'] = ! (bool) $config['case_sensitive'];
                unset($config['case_sensitive']);
            }

            return $config;
        },
    );
}
```

If you want a lower-risk rollout, keep read compatibility for `case_sensitive` in the request/model for one deploy cycle, backfill stored JSON, and then remove the alias.

## Risk Level (High / Medium / Low)

Medium

This is unlikely to break every automation immediately because the currently inspected frontend, validator, and engine all use `case_sensitive`. The risk is still material because the contract is ambiguous and not normalized: any stale client, integration, or existing row using `case_insensitive` will either fail validation or be interpreted with the default matching behavior, producing false positives or false negatives in keyword automations.
