<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlatformAdmin;
use App\Models\PlatformAdminAuditLog;
use Illuminate\Http\Request;

final class PlatformAudit
{
    public static function log(
        PlatformAdmin $admin,
        string $action,
        ?string $targetType = null,
        ?string $targetId   = null,
        array $metadata     = [],
        ?Request $request   = null
    ): void {
        PlatformAdminAuditLog::create([
            'platform_admin_id' => $admin->id,
            'action'            => $action,
            'target_type'       => $targetType,
            'target_id'         => $targetId,
            'ip_address'        => $request?->ip(),
            'metadata'          => empty($metadata) ? null : $metadata,
        ]);
    }
}
