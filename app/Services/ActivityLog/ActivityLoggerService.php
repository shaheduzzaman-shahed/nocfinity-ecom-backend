<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ActivityLoggerService
{
    /**
     * Keys to automatically strip from payloads to protect credentials and secrets.
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'client_secret',
        'remember_token',
    ];

    /**
     * Persist an activity log record into the database.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $payload = null,
        ?User $user = null,
        ?string $ipAddress = null,
    ): ?ActivityLog {
        try {
            $userId = $user?->id ?? Auth::id();
            $ip = $ipAddress ?? Request::ip();

            $sanitizedPayload = $payload !== null
                ? $this->sanitizePayload($payload)
                : null;

            return ActivityLog::create([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entity ? get_class($entity) : null,
                'entity_id' => $entity?->getKey(),
                'payload' => $sanitizedPayload,
                'ip_address' => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to store activity log: '.$e->getMessage(), [
                'action' => $action,
                'user_id' => $user?->id ?? Auth::id(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Deep sanitization of payload arrays removing sensitive keys.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
