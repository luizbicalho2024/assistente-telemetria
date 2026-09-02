<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(string $action, array $details = [], ?Request $request = null): void
    {
        $request ??= request();
        ActivityLog::query()->create([
            'timestamp' => now(),
            'user' => Auth::user()?->username ?? Auth::user()?->email ?? 'sistema',
            'user_id' => Auth::id(),
            'action' => $action,
            'details' => $details,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 500),
        ]);
    }
}
