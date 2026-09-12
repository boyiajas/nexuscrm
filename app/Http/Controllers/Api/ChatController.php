<?php

namespace App\Http\Controllers\Api;

use App\Concerns\EnforcesMetaPermissionHealth;
use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\CampaignWhatsappRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    use EnforcesMetaPermissionHealth;

    public function __construct(private WhatsAppServiceInterface $whatsApp)
    {
    }

    public function index(Request $request)
    {
        $user = $this->authorizeView();

        $query = ChatSession::with(['client', 'agent', 'latestMessage'])
            ->orderByDesc('updated_at');

        $bankIds = [];
        $allowedWabaPhoneIds = [];
        if (!$user->canAccessAllBanks()) {
            $bankIds = $user->accessibleBankIds();
            if (empty($bankIds)) {
                $bankIds = $user->resolvedBankIds();
            }

            if (empty($bankIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $allowedWabaPhoneIds = \App\Models\WhatsappAccount::whereIn('bank_id', $bankIds)
                    ->pluck('phone_number_id')
                    ->filter()
                    ->map(fn($id) => (string) $id)
                    ->all();

                $query->where(function ($q) use ($bankIds, $allowedWabaPhoneIds) {
                    $q->whereIn('bank_id', $bankIds)
                      ->orWhereHas('client', function ($cq) use ($bankIds) {
                          $cq->whereIn('bank_id', $bankIds);
                      });

                    if (!empty($allowedWabaPhoneIds)) {
                        $q->orWhereIn('waba_phone_number_id', $allowedWabaPhoneIds);
                    }
                });
            }
        }

        if ($user->isPortfolioScoped()) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'unread') {
                $query->where('unread_count', '>', 0);
            } elseif ($status === 'read') {
                $query->where(function ($q) {
                    $q->where('unread_count', 0)->orWhereNull('unread_count');
                });
            } elseif ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($departmentId = $request->get('department_id')) {
            if ($departmentId !== 'all') {
                $query->whereHas('client.departments', function ($q) use ($departmentId) {
                    $q->where('departments.id', $departmentId);
                });
            }
        }

        if ($bankId = $request->get('bank_id')) {
            if ($bankId !== 'all') {
                if (!$user->canAccessAllBanks()) {
                    if (!in_array((int) $bankId, $bankIds, true)) {
                        abort(403, 'You do not have access to this bank.');
                    }
                }
                $query->where('bank_id', $bankId);
            }
        }

        if ($wabaNumber = $request->get('waba_number')) {
            if ($wabaNumber !== 'all') {
                if (!$user->canAccessAllBanks()) {
                    if (!in_array((string) $wabaNumber, $allowedWabaPhoneIds, true)) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('waba_phone_number_id', $wabaNumber);
                    }
                } else {
                    $query->where('waba_phone_number_id', $wabaNumber);
                }
            }
        }

        if ($search = $request->get('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('last_message', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('id_number', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->get('per_page', 100), 500);

        return $query->paginate($perPage);
    }

    public function filters()
    {
        $user = $this->authorizeView();

        $banksQuery = \App\Models\Bank::select('id', 'name');
        if (!$user->canAccessAllBanks()) {
            $userBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            if (empty($userBankIds)) {
                $banksQuery->whereRaw('1 = 0');
            } else {
                $banksQuery->whereIn('id', $userBankIds);
            }
        }
        $banks = $banksQuery->get();

        $departmentsQuery = \App\Models\Department::select('id', 'name');
        if (!$user->canAccessAllBanks() && !empty($user->resolvedDepartmentIds())) {
             $departmentsQuery->whereIn('id', $user->resolvedDepartmentIds());
        }
        $departments = $departmentsQuery->get();

        // 1. Fetch configured accounts from database
        $accounts = \App\Models\WhatsappAccount::with('bank:id,name')->get();

        // 2. Fetch live senders from WhatsApp service
        $liveSenders = [];
        try {
            $liveSenders = $this->whatsApp->listWhatsappSenders();
        } catch (\Throwable $e) {
            Log::error('Failed to load WABAs for chat filters: ' . $e->getMessage());
        }

        // 3. Build unified WABA senders list with bank metadata
        $wabas = [];
        $seenPhoneIds = [];

        foreach ($liveSenders as $s) {
            $pId = (string) ($s['phone_number_id'] ?? '');
            $num = $s['number'] ?? '';

            // Match against database WhatsappAccount
            $acc = $accounts->first(function ($a) use ($pId, $num) {
                if ($pId && (string) $a->phone_number_id === $pId) return true;
                if ($num && \App\Services\MetaWhatsAppService::normalizePhoneNumber($a->display_phone_number) === \App\Services\MetaWhatsAppService::normalizePhoneNumber($num)) return true;
                return false;
            });

            $bankId = $acc?->bank_id;
            $bankName = $acc?->bank?->name;

            $wabas[] = [
                'number' => $num,
                'label' => $acc?->name ?: $s['label'],
                'default' => $s['default'] ?? false,
                'phone_number_id' => $pId,
                'bank_id' => $bankId,
                'bank_name' => $bankName,
            ];

            if ($pId) {
                $seenPhoneIds[] = $pId;
            }
        }

        // Include any database accounts not returned by live Meta service call
        foreach ($accounts as $acc) {
            $pId = (string) $acc->phone_number_id;
            if ($pId && !in_array($pId, $seenPhoneIds, true)) {
                $wabas[] = [
                    'number' => $acc->display_phone_number ?: $acc->phone_number_id,
                    'label' => $acc->name,
                    'default' => false,
                    'phone_number_id' => $acc->phone_number_id,
                    'bank_id' => $acc->bank_id,
                    'bank_name' => $acc->bank?->name,
                ];
                $seenPhoneIds[] = $pId;
            }
        }

        // 4. If user is bank-restricted, strictly filter WABA numbers to their assigned banks
        if (!$user->canAccessAllBanks()) {
            $userBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            $wabas = array_values(array_filter($wabas, function ($w) use ($userBankIds) {
                return !empty($w['bank_id']) && in_array((int) $w['bank_id'], $userBankIds, true);
            }));
        }

        $settings = \App\Models\SystemSetting::first();
        $liveChatLocked = $settings ? (bool) $settings->live_chat_locked : false;
        $liveChatLockedMessage = $settings ? $settings->live_chat_locked_message : 'Live chat is temporarily disabled.';

        return response()->json(compact('banks', 'departments', 'wabas', 'liveChatLocked', 'liveChatLockedMessage'));
    }

    public function show(ChatSession $session)
    {
        $user = $this->authorizeView();
        $this->authorizeSessionScope($user, $session);

        $session->load(['messages' => function ($q) {
            $q->orderBy('sent_at', 'asc');
        }, 'agent', 'client']);

        // Keep review-only roles read-only by skipping unread-count mutation.
        if (!$user->isReadOnlyRole()) {
            $session->update(['unread_count' => 0]);
        }

        return $this->appendCampaignMessages($session);
    }

    public function storeMessage(Request $request, ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);

        $data = $request->validate([
            'content'     => ['nullable', 'string'],
            'file'        => ['nullable', 'file', 'max:25600'],
            'is_template' => ['sometimes', 'boolean'],
        ]);

        if (!$request->filled('content') && !$request->hasFile('file')) {
            return response()->json(['message' => 'Either text content or a file attachment is required.'], 422);
        }

        $settings = \App\Models\SystemSetting::first();
        if ($settings && $settings->live_chat_locked) {
            return response()->json([
                'message' => $settings->live_chat_locked_message ?: 'Live chat is temporarily disabled.'
            ], 403);
        }

        if ($session->platform === 'whatsapp') {
            $this->enforceMetaPermissionHealthForProduction('Live chat WhatsApp sending');
        }

        $mediaUrl = null;
        $mediaType = null;
        $originalFilename = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $mime = $file->getClientMimeType() ?: 'application/octet-stream';
            
            if (str_starts_with($mime, 'image/')) {
                $mediaType = 'image';
            } elseif (str_starts_with($mime, 'video/')) {
                $mediaType = 'video';
            } elseif (str_starts_with($mime, 'audio/')) {
                $mediaType = 'audio';
            } else {
                $mediaType = 'document';
            }

            $path = $file->store('chat_attachments', 'public');
            $mediaUrl = Storage::disk('public')->url($path);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '' && $mediaUrl) {
            $content = match ($mediaType) {
                'image' => '[📷 Image Attachment]',
                'video' => '[🎥 Video Attachment]',
                'audio' => '[🎵 Audio Attachment]',
                default => "[📄 {$originalFilename}]",
            };
        }

        $message = $session->messages()->create([
            'sender'      => 'agent',
            'content'     => $content,
            'media_url'   => $mediaUrl,
            'media_type'  => $mediaType,
            'is_template' => $data['is_template'] ?? false,
            'sent_at'     => now(),
        ]);

        $session->update([
            'last_message' => $content,
            'updated_at'   => now(),
        ]);

        // Try sending outbound WhatsApp for live chat sessions
        if ($session->platform === 'whatsapp') {
            if ($mediaUrl) {
                $this->sendWhatsappMediaReply($session, $mediaType, $mediaUrl, $data['content'] ?? null, $originalFilename);
            } else {
                $this->sendWhatsappReply($session, $content);
            }
        }

        return response()->json($message, 201);
    }

    public function destroy(ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);
        
        $session->delete();
        
        return response()->json(['message' => 'Chat session deleted successfully.']);
    }

    // Reserved for future manual webhook entry points if needed:
    public function receiveFromClient(Request $request)
    {
        // TODO: handle inbound webhook payloads, find/create ChatSession,
        // create ChatMessage with sender='user', increment unread_count, etc.
    }

    /**
     * Ensure a chat session exists for a client and return it with messages.
     */
    public function sessionForClient(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'platform'  => ['sometimes', 'string', 'max:50'],
            'waba_number' => ['sometimes', 'string', 'max:255'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $this->authorizeClientScope($request->user(), $client);
        $platform = $data['platform'] ?? 'whatsapp';

        $attributes = [
            'client_name' => $client->name,
            'bank_id' => $client->bank_id,
            'status'      => 'active',
            'agent_id'    => Auth::id(),
            'unread_count'=> 0,
        ];

        if (!empty($data['waba_number'])) {
            $attributes['waba_phone_number_id'] = $data['waba_number'];
        }

        $session = ChatSession::firstOrCreate(
            [
                'client_id' => $client->id,
                'platform'  => $platform,
            ],
            $attributes
        );

        if (!empty($data['waba_number']) && $session->waba_phone_number_id !== $data['waba_number']) {
            $session->update(['waba_phone_number_id' => $data['waba_number']]);
        }

        // Load messages ordered and reset unread count when fetched
        $session->load(['client', 'agent', 'messages' => function ($q) {
            $q->orderBy('created_at');
        }]);

        $session->update(['unread_count' => 0]);

        return response()->json($this->appendCampaignMessages($session));
    }

    public function clear(ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);
        
        $session->messages()->delete();
        $session->update(['last_message' => null, 'last_message_at' => null, 'unread_count' => 0]);
        
        return response()->json(['message' => 'Chat cleared successfully.']);
    }

    public function block(ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);
        
        $client = \App\Models\Client::find($session->client_id);
        if ($client) {
            $client->update([
                'whatsapp_opted_out_at' => now(),
                'whatsapp_opt_out_reason' => 'Blocked via Live Chat interface',
                'status' => 'Blocked',
            ]);
        }
        
        $session->delete();
        
        return response()->json(['message' => 'Client blocked and chat session removed.']);
    }

    protected function appendCampaignMessages(ChatSession $session)
    {
        if ($session->platform !== 'whatsapp') {
            return $session;
        }

        $client = $session->client;
        if (!$client) {
            return $session;
        }

        $recipients = CampaignWhatsappRecipient::where('client_id', $client->id)
            ->where(function ($query) {
                $query->whereIn('status', ['Sent', 'Delivered', 'Read', 'sent', 'delivered', 'read'])
                    ->orWhereNotNull('delivered_at')
                    ->orWhereNotNull('last_attempted_at');
            })
            ->with(['message', 'message.campaign'])
            ->orderBy('created_at')
            ->get();

        $campaignMessages = collect();

        foreach ($recipients as $recipient) {
            $batch = $recipient->message;
            if (!$batch) continue;

            $sentAt = $recipient->delivered_at ?: ($recipient->last_attempted_at ?: ($recipient->created_at ?: $batch?->sent_at));
            $body = $batch->preview_body ?: "Template: " . ($batch->template_name ?: 'Campaign Message');
            $campaignName = $batch->campaign?->name ?: 'WhatsApp Campaign';

            $campaignMessages->push([
                'id' => 'campaign_' . $recipient->id,
                'chat_session_id' => $session->id,
                'sender' => 'agent',
                'content' => "📢 [Campaign: {$campaignName}]\n{$body}",
                'is_template' => true,
                'sent_at' => $sentAt ? $sentAt->toIso8601String() : null,
                'created_at' => $sentAt ? $sentAt->toIso8601String() : null,
                'updated_at' => $sentAt ? $sentAt->toIso8601String() : null,
            ]);
        }

        $merged = collect($session->messages)->map(function ($msg) {
            return is_array($msg) ? $msg : $msg->toArray();
        })->concat($campaignMessages)->sortBy(function ($msg) {
            return $msg['sent_at'] ?? $msg['created_at'] ?? '';
        })->values();

        $session->setRelation('messages', $merged);

        return $session;
    }

    protected function authorizeView()
    {
        $user = Auth::user();

        if (!$user || !$user->canViewLiveChat()) {
            abort(403, 'You are not allowed to access live chat.');
        }

        return $user;
    }

    protected function authorizeManage(): void
    {
        $user = Auth::user();

        if (!$user || !$user->canSendWhatsapp()) {
            abort(403, 'You are not allowed to manage live chat.');
        }
    }

    protected function authorizeSessionScope($user, ChatSession $session): void
    {
        if (!$user->canAccessAllBanks()) {
            $bankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            if (empty($bankIds)) {
                abort(403, 'You do not have permission to act on this chat session.');
            }

            $sessionBankId = $session->bank_id ?: $session->client?->bank_id;
            $hasBankMatch = $sessionBankId && in_array((int) $sessionBankId, $bankIds, true);

            $hasWabaMatch = false;
            if ($session->waba_phone_number_id) {
                $hasWabaMatch = \App\Models\WhatsappAccount::whereIn('bank_id', $bankIds)
                    ->where('phone_number_id', $session->waba_phone_number_id)
                    ->exists();
            }

            if (!$hasBankMatch && !$hasWabaMatch) {
                abort(403, 'You do not have permission to act on this chat session.');
            }
        }

        if ($user->isPortfolioScoped() && $session->client && (int) $session->client->assigned_to_id !== (int) $user->id) {
            abort(403, 'You are not allowed to access this chat session.');
        }
    }

    protected function authorizeClientScope($user, Client $client): void
    {
        if (!$user->canAccessAllBanks() && !empty($user->resolvedBankIds()) && !in_array((int) $client->bank_id, $user->resolvedBankIds(), true)) {
            abort(403, 'You do not have permission to start a chat with this client.');
        }

        if ($user->isPortfolioScoped() && (int) $client->assigned_to_id !== (int) $user->id) {
            abort(403, 'You are not allowed to access this client chat.');
        }
    }

    protected function sendWhatsappReply(ChatSession $session, string $body): void
    {
        $client = $session->client;
        $to = $client?->phone ?: $session->phone;
        if (!$to) {
            Log::warning('Chat WhatsApp reply skipped: no phone on session', ['session_id' => $session->id]);
            return;
        }

        try {
            Log::info('Chat WhatsApp reply attempt', [
                'session_id' => $session->id,
                'client_id' => $session->client_id,
                'to' => $to,
                'body_length' => mb_strlen($body),
            ]);

            $senderContext = method_exists($this->whatsApp, 'resolveSenderForClient')
                ? $this->whatsApp->resolveSenderForClient($client)
                : null;
            $overrideFrom = $senderContext['display_phone_number'] ?? null;

            $this->whatsApp->sendPlainWhatsapp($to, $body, $overrideFrom);
        } catch (\Throwable $e) {
            Log::error('Failed to send WhatsApp chat reply', [
                'session_id' => $session->id,
                'to'         => $to,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function sendWhatsappMediaReply(ChatSession $session, string $mediaType, string $mediaUrl, ?string $caption = null, ?string $filename = null): void
    {
        $client = $session->client;
        $to = $client?->phone ?: $session->phone;
        if (!$to) {
            Log::warning('Chat WhatsApp media reply skipped: no phone on session', ['session_id' => $session->id]);
            return;
        }

        try {
            Log::info('Chat WhatsApp media reply attempt', [
                'session_id' => $session->id,
                'client_id' => $session->client_id,
                'to' => $to,
                'media_type' => $mediaType,
                'media_url' => $mediaUrl,
            ]);

            $senderContext = method_exists($this->whatsApp, 'resolveSenderForClient')
                ? $this->whatsApp->resolveSenderForClient($client)
                : null;
            $overrideFrom = $senderContext['display_phone_number'] ?? null;

            if (method_exists($this->whatsApp, 'sendMediaWhatsapp')) {
                $this->whatsApp->sendMediaWhatsapp($to, $mediaType, $mediaUrl, $caption, $filename, $overrideFrom);
            } else {
                $this->whatsApp->sendPlainWhatsapp($to, $caption ?: "[Attachment: {$mediaUrl}]", $overrideFrom);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send WhatsApp chat media reply', [
                'session_id' => $session->id,
                'to'         => $to,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function updateOptIn(Request $request, ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);

        $data = $request->validate([
            'opt_in' => ['required', 'string', \Illuminate\Validation\Rule::in(['yes', 'no', 'none'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $client = $session->client;
        if ($client) {
            $client->setOptIn($data['opt_in'], $data['reason'] ?? 'Updated via Live Chat');
        }

        return response()->json([
            'message' => 'Opt-in status updated successfully',
            'opt_in' => $client?->opt_in ?: $data['opt_in'],
            'opt_in_updated_at' => optional($client?->opt_in_updated_at)->toDateTimeString(),
        ]);
    }
}
