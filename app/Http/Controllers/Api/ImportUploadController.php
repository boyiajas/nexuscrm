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

        if (!$user->canAccessAllBanks() && !empty($user->resolvedBankIds())) {
            $query->whereIn('bank_id', $user->resolvedBankIds());
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

    protected function authorizeView(?User $user): void
    {
        abort_unless($user && $user->canViewImportUploads(), 403, 'You are not allowed to access import upload records.');
    }
}
