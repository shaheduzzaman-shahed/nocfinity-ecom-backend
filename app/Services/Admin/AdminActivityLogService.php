<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AdminActivityLogService
{
    /**
     * Paginate activity logs with comprehensive filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateLogs(array $filters = []): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->with(['user:id,name,email,account_type,avatar']);

        // Search term
        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('action', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('entity_type', 'like', $term)
                    ->orWhereHas('user', function (Builder $userQuery) use ($term) {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }

        // Filter by user ID
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        // Filter by user account type
        if (! empty($filters['account_type'])) {
            $accountType = $filters['account_type'];
            $query->whereHas('user', function (Builder $userQuery) use ($accountType) {
                $userQuery->where('account_type', $accountType);
            });
        }

        // Filter by action slug or prefix
        if (! empty($filters['action'])) {
            $action = trim((string) $filters['action']);
            if (str_ends_with($action, '*')) {
                $prefix = rtrim($action, '*');
                $query->where('action', 'like', $prefix.'%');
            } else {
                $query->where('action', $action);
            }
        }

        // Filter by entity type
        if (! empty($filters['entity_type'])) {
            $entityType = trim((string) $filters['entity_type']);
            if (! str_contains($entityType, '\\')) {
                $query->where(function (Builder $q) use ($entityType) {
                    $q->where('entity_type', $entityType)
                        ->orWhere('entity_type', 'like', '%\\'.$entityType);
                });
            } else {
                $query->where('entity_type', $entityType);
            }
        }

        // Filter by entity ID
        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', (int) $filters['entity_id']);
        }

        // Filter by date range
        if (! empty($filters['date_from'])) {
            try {
                $from = Carbon::parse($filters['date_from'])->startOfDay();
                $query->where('created_at', '>=', $from);
            } catch (\Throwable) {
                // Ignore invalid date
            }
        }

        if (! empty($filters['date_to'])) {
            try {
                $to = Carbon::parse($filters['date_to'])->endOfDay();
                $query->where('created_at', '<=', $to);
            } catch (\Throwable) {
                // Ignore invalid date
            }
        }

        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sortDirection)->orderBy('id', $sortDirection);

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Get distinct action slugs for UI dropdown filtering.
     *
     * @return array<int, string>
     */
    public function distinctActions(): array
    {
        return ActivityLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values()
            ->all();
    }

    /**
     * Get single activity log with relations.
     */
    public function getLog(int $id): ActivityLog
    {
        return ActivityLog::query()
            ->with(['user:id,name,email,account_type,avatar'])
            ->findOrFail($id);
    }
}
