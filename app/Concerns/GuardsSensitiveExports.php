<?php

namespace App\Concerns;

use App\Models\ExportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait GuardsSensitiveExports
{
    protected function authorizeSensitiveExport(Request $request, string $dataset, ?string $targetType = null, ?int $targetId = null): ?ExportRequest
    {
        $user = Auth::user();

        if ($user && $user->canBypassExportApproval()) {
            return null;
        }

        $exportRequestId = (int) $request->query('export_request_id');
        if (!$exportRequestId) {
            abort(423, 'This export requires an approved export request.');
        }

        $exportRequest = ExportRequest::query()->findOrFail($exportRequestId);

        if ((int) $exportRequest->requested_by_user_id !== (int) $user?->id) {
            abort(403, 'You are not allowed to use this export request.');
        }

        if ($exportRequest->status !== ExportRequest::STATUS_APPROVED) {
            abort(423, 'This export request is not approved for download.');
        }

        if ($exportRequest->dataset !== $dataset) {
            abort(403, 'This export request does not match the requested dataset.');
        }

        if (($exportRequest->target_type ?: null) !== ($targetType ?: null) || (int) ($exportRequest->target_id ?: 0) !== (int) ($targetId ?: 0)) {
            abort(403, 'This export request does not match the requested target.');
        }

        if ($this->normalizeExportFilters($request->query()) !== $this->normalizeExportFilters($exportRequest->filters ?? [])) {
            abort(403, 'This export request does not match the requested filters.');
        }

        return $exportRequest;
    }

    protected function markSensitiveExportCompleted(?ExportRequest $exportRequest, string $fileName): void
    {
        if (!$exportRequest) {
            return;
        }

        $exportRequest->forceFill([
            'status' => ExportRequest::STATUS_DOWNLOADED,
            'downloaded_by_user_id' => Auth::id(),
            'downloaded_at' => now(),
            'download_filename' => $fileName,
        ])->save();

        if (method_exists($this, 'audit')) {
            $this->audit(
                action: 'Downloaded approved export',
                module: 'Export Requests',
                meta: [
                    'export_request_id' => $exportRequest->id,
                    'dataset' => $exportRequest->dataset,
                    'bank_id' => $exportRequest->bank_id,
                    'download_filename' => $fileName,
                ]
            );
        }
    }

    protected function normalizeExportFilters(array $filters): array
    {
        unset($filters['export_request_id'], $filters['page'], $filters['per_page']);

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $filters[$key] = $this->normalizeExportFilters($value);
            } elseif ($value === null) {
                unset($filters[$key]);
            } else {
                $filters[$key] = (string) $value;
            }
        }

        ksort($filters);

        return $filters;
    }
}
