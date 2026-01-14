<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        // Filters
        if ($module = $request->get('module')) {
            if ($module !== 'all') {
                $query->where('module', $module);
            }
        }

        if ($userId = $request->get('user_id')) {
            if ($userId !== 'all') {
                $query->where('user_id', $userId);
            }
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('logged_at', '>=', Carbon::parse($from)->toDateString());
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('logged_at', '<=', Carbon::parse($to)->toDateString());
        }

        if ($q = $request->get('q')) {
            $query->where(function ($qBuilder) use ($q) {
                $qBuilder->where('action', 'like', "%$q%")
                    ->orWhere('module', 'like', "%$q%")
                    ->orWhere('ip_address', 'like', "%$q%")
                    ->orWhere('meta', 'like', "%$q%");
            });
        }

        $perPage = (int) $request->get('per_page', 20);

        $logs = $query->orderByDesc('logged_at')->paginate($perPage);

        // Shape data slightly for frontend convenience
        $logs->getCollection()->transform(function ($log) {
            return [
                'id'         => $log->id,
                'user_name'  => optional($log->user)->name,
                'action'     => $log->action,
                'module'     => $log->module,
                'ip_address' => $log->ip_address,
                'logged_at'  => optional($log->logged_at)->toDateTimeString(),
            ];
        });

        return $logs;
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user');

        return [
            'id'         => $auditLog->id,
            'user_name'  => optional($auditLog->user)->name,
            'action'     => $auditLog->action,
            'module'     => $auditLog->module,
            'ip_address' => $auditLog->ip_address,
            'logged_at'  => optional($auditLog->logged_at)->toDateTimeString(),
            'created_at' => optional($auditLog->created_at)->toDateTimeString(),
            'updated_at' => optional($auditLog->updated_at)->toDateTimeString(),
            'meta'       => $auditLog->meta,
        ];
    }

    public function export(Request $request): StreamedResponse
    {
        $fileName = 'audit_logs_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($request) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'ID',
                'User',
                'Module',
                'Action',
                'IP Address',
                'Logged At',
            ]);

            $query = AuditLog::with('user');

            // same filters as index()
            if ($module = $request->get('module')) {
                if ($module !== 'all') {
                    $query->where('module', $module);
                }
            }

            if ($userId = $request->get('user_id')) {
                if ($userId !== 'all') {
                    $query->where('user_id', $userId);
                }
            }

            if ($from = $request->get('date_from')) {
                $query->whereDate('logged_at', '>=', Carbon::parse($from)->toDateString());
            }

            if ($to = $request->get('date_to')) {
                $query->whereDate('logged_at', '<=', Carbon::parse($to)->toDateString());
            }

            if ($q = $request->get('q')) {
                $query->where(function ($qBuilder) use ($q) {
                    $qBuilder->where('action', 'like', "%$q%")
                        ->orWhere('module', 'like', "%$q%")
                        ->orWhere('ip_address', 'like', "%$q%")
                        ->orWhere('meta', 'like', "%$q%");
                });
            }

            $query->orderByDesc('logged_at')->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        optional($log->user)->name,
                        $log->module,
                        $log->action,
                        $log->ip_address,
                        optional($log->logged_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
