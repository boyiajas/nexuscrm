<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportUpload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportUploadController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $this->authorizeView($user);

        $query = ImportUpload::with(['bank:id,name', 'user:id,name,role'])
            ->latest();

        if (!$user->canAccessAllBanks()) {
            $bankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            if (empty($bankIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('bank_id', $bankIds);
            }
        }

        if (!$user->canViewAllImportedClients()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('bank_id') && $user->canAccessAllBanks()) {
            $query->where('bank_id', (int) $request->get('bank_id'));
        }

        if ($status = $request->get('import_status')) {
            if (!in_array($status, ['all', ''], true)) {
                $query->where('import_status', $status);
            }
        }

        if ($scanStatus = $request->get('scan_status')) {
            if (!in_array($scanStatus, ['all', ''], true)) {
                $query->where('scan_status', $scanStatus);
            }
        }

        if ($q = trim((string) $request->get('q'))) {
            $query->where(function ($inner) use ($q) {
                $inner->where('original_filename', 'like', "%{$q}%")
                    ->orWhere('import_batch_number', 'like', "%{$q}%")
                    ->orWhere('dataset', 'like', "%{$q}%")
                    ->orWhere('error_message', 'like', "%{$q}%")
                    ->orWhere('scan_signature', 'like', "%{$q}%");
            });
        }

        return $query->paginate((int) $request->get('per_page', 15));
    }

    public function reprocess(Request $request, ImportUpload $importUpload)
    {
        $user = Auth::user();
        abort_unless($user && $user->canImportClients(), 403, 'You do not have permission to reprocess imports.');

        $filePath = $importUpload->stored_path;
        $candidates = [
            $filePath,
            \Illuminate\Support\Facades\Storage::disk('local')->path((string) $filePath),
            storage_path('app/' . ltrim((string) $filePath, '/')),
            storage_path('app/private/' . ltrim((string) $filePath, '/')),
            base_path('docs/' . $importUpload->original_filename),
        ];

        $resolvedPath = null;
        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                $resolvedPath = $candidate;
                break;
            }
        }

        if (!$resolvedPath) {
            return response()->json([
                'message' => 'The original import file could not be found on disk.',
            ], 404);
        }

        $importUpload->update(['import_status' => 'importing']);

        \App\Jobs\ImportClientsJob::dispatch(
            $importUpload->id,
            $user->id,
            $importUpload->bank_id,
            $importUpload->department_ids ?? [],
            $resolvedPath,
            $importUpload->original_filename,
            $importUpload->import_batch_number,
            ['status' => 'skipped']
        )->onQueue('imports');

        return response()->json([
            'message' => "Reprocessing queued for batch {$importUpload->import_batch_number}.",
            'import_batch_number' => $importUpload->import_batch_number,
        ]);
    }

    protected function authorizeView(?User $user): void
    {
        abort_unless($user && $user->canViewImportUploads(), 403, 'You are not allowed to access import upload records.');
    }
}
