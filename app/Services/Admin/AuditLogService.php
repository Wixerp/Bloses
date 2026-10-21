<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuditLogService
{
    /**
     * Record a business action explicitly from a controller or service.
     */
    public function record(
        Request $request,
        string $action,
        string $module,
        ?string $description = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $user = $request->user() ?? Auth::user();

        // Never store credentials, tokens, or other sensitive fields.
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'mail_password',
            'sms_api_key',
            'api_key',
            'secret',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'remember_token',
        ];

        $sanitize = function (?array $values) use (
            $sensitiveKeys
        ): ?array {
            if ($values === null) {
                return null;
            }

            foreach (array_keys($values) as $key) {
                if (
                    in_array(strtolower((string) $key), $sensitiveKeys, true)
                    || str_contains(strtolower((string) $key), 'password')
                    || str_contains(strtolower((string) $key), 'token')
                    || str_contains(strtolower((string) $key), 'secret')
                ) {
                    unset($values[$key]);
                }
            }

            return $values;
        };

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->getRoleNames()->implode(', '),

            'action' => $action,
            'module' => $module,
            'description' => $description,

            'auditable_type' => $subject
                ? get_class($subject)
                : null,
            'auditable_id' => $subject?->getKey(),

            'old_values' => $sanitize($oldValues),
            'new_values' => $sanitize($newValues),

            'ip_address' => $request->ip(),
            'user_agent' => substr(
                (string) $request->userAgent(),
                0,
                5000
            ),
            'request_method' => $request->method(),
            'request_url' => substr($request->fullUrl(), 0, 5000),
        ]);
    }

    public function index(array $filters = [])
    {
        $query = AuditLog::query()
            ->when(
                !empty($filters['user_id']),
                fn (Builder $q) => $q->where(
                    'user_id',
                    $filters['user_id']
                )
            )
            ->when(
                !empty($filters['action']),
                fn (Builder $q) => $q->where(
                    'action',
                    $filters['action']
                )
            )
            ->when(
                !empty($filters['module']),
                fn (Builder $q) => $q->where(
                    'module',
                    $filters['module']
                )
            )
            ->when(
                !empty($filters['from_date']),
                fn (Builder $q) => $q->whereDate(
                    'created_at',
                    '>=',
                    $filters['from_date']
                )
            )
            ->when(
                !empty($filters['to_date']),
                fn (Builder $q) => $q->whereDate(
                    'created_at',
                    '<=',
                    $filters['to_date']
                )
            )
            ->when(
                !empty($filters['search']),
                function (Builder $q) use ($filters) {
                    $search = $filters['search'];

                    $q->where(function (Builder $nested) use ($search) {
                        $nested
                            ->where('user_name', 'ILIKE', "%{$search}%")
                            ->orWhere('user_email', 'ILIKE', "%{$search}%")
                            ->orWhere('description', 'ILIKE', "%{$search}%")
                            ->orWhere('auditable_type', 'ILIKE', "%{$search}%");
                    });
                }
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query->paginate(
            (int) ($filters['per_page'] ?? 20)
        );
    }

    public function show(int $id): AuditLog
    {
        return AuditLog::findOrFail($id);
    }
}
