<?php

namespace App\Http\Controllers\Api;

use App\Concerns\AppliesAccessScopes;
use App\Concerns\EnforcesMetaPermissionHealth;
use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\CampaignWhatsappRecipient;
use App\Models\WhatsappTemplateCache;
use App\Services\BankWabaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    use AppliesAccessScopes;
    use EnforcesMetaPermissionHealth;

    public function __construct(private WhatsAppServiceInterface $whatsApp)
    {
    }

    public function index(Request $request)
    {
        $user = $this->authorizeView();

        $query = ChatSession::with(['client', 'agent', 'latestMessage'])
            ->orderByDesc('updated_at');

        $this->scopeChatSessionQueryToUser($query, $user);

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
                if (!$user->canAccessAllBanks()) {
                    $userDeptIds = $user->resolvedDepartmentIds();
                    if (!in_array((int) $departmentId, $userDeptIds, true)) {
                        abort(403, 'You do not have access to this department.');
                    }
                }
                $query->whereHas('client.departments', function ($q) use ($departmentId) {
                    $q->where('departments.id', $departmentId);
                });
            }
        }

        if ($bankId = $request->get('bank_id')) {
            if ($bankId !== 'all') {
                if (!$user->canAccessAllBanks()) {
                    $userBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                    if (!in_array((int) $bankId, $userBankIds, true)) {
                        abort(403, 'You do not have access to this bank.');
                    }
                }
                $query->where('bank_id', $bankId);
            }
        }

        if ($wabaNumber = $request->get('waba_number')) {
            if ($wabaNumber !== 'all') {
                if (!$user->canAccessAllBanks()) {
                    $userBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                    $resolver = app(BankWabaResolver::class);
                    $allowedWabaPhoneIds = $resolver->getAllowedWabaPhoneIdsForBanks($userBankIds);
                    $bankNumbers = $resolver->getPhoneNumbersForBanks($userBankIds);

                    if (!in_array((string) $wabaNumber, $allowedWabaPhoneIds, true) && !in_array((string) $wabaNumber, $bankNumbers, true)) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('waba_phone_number_id', $wabaNumber);
                    }
                } else {
                    $query->where('waba_phone_number_id', $wabaNumber);
                }
            }
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('last_message', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('cell_phone', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('easy_pay_number', 'like', "%{$search}%")
                        ->orWhere('store_number', 'like', "%{$search}%")
                        ->orWhere('id_number', 'like', "%{$search}%");
                  });
            });

            $perPage = min((int) $request->get('per_page', 100), 500);
            $sessions = $query->take($perPage)->get();
            $existingClientIds = $sessions->pluck('client_id')->filter()->unique()->values()->all();

            $statusFilter = $request->get('status');
            $includeSystemClients = empty($statusFilter) || in_array($statusFilter, ['all', 'active'], true);

            $clientItems = collect();

            if ($includeSystemClients) {
                $clientQuery = Client::query()->with(['departments', 'bank', 'assignedTo:id,name,bank_id']);
                $this->scopeClientQueryToUser($clientQuery, $user);

                if ($departmentId = $request->get('department_id')) {
                    if ($departmentId !== 'all') {
                        if (!$user->canAccessAllBanks()) {
                            $userDeptIds = $user->resolvedDepartmentIds();
                            if (!in_array((int) $departmentId, $userDeptIds, true)) {
                                abort(403, 'You do not have access to this department.');
                            }
                        }
                        $clientQuery->whereHas('departments', function ($q) use ($departmentId) {
                            $q->where('departments.id', $departmentId);
                        });
                    }
                }

                if ($bankId = $request->get('bank_id')) {
                    if ($bankId !== 'all') {
                        if (!$user->canAccessAllBanks()) {
                            $userBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                            if (!in_array((int) $bankId, $userBankIds, true)) {
                                abort(403, 'You do not have access to this bank.');
                            }
                        }
                        $clientQuery->where('clients.bank_id', $bankId);
                    }
                }

                $clientQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('surname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('cell_phone', 'like', "%{$search}%")
                      ->orWhere('id_number', 'like', "%{$search}%")
                      ->orWhere('account_number', 'like', "%{$search}%")
                      ->orWhere('easy_pay_number', 'like', "%{$search}%")
                      ->orWhere('store_number', 'like', "%{$search}%");
                });

                if (!empty($existingClientIds)) {
                    $clientQuery->whereNotIn('clients.id', $existingClientIds);
                }

                $matchingClients = $clientQuery->take(50)->get();
                $matchingClients->load(['chatSessions' => function ($q) {
                    $q->with(['agent', 'latestMessage'])->latest('updated_at');
                }]);

                foreach ($matchingClients as $client) {
                    $existingSession = $client->chatSessions->first();
                    if ($existingSession && !$sessions->contains('id', $existingSession->id)) {
                        $existingSession->setRelation('client', $client);
                        $clientItems->push($existingSession);
                    } elseif (!$existingSession) {
                        $clientItems->push([
                            'id' => 'client_' . $client->id,
                            'is_client_only' => true,
                            'client_id' => $client->id,
                            'client_name' => $client->name,
                            'phone' => $client->phone ?: $client->cell_phone,
                            'status' => 'client',
                            'platform' => 'whatsapp',
                            'waba_phone_number_id' => null,
                            'last_message' => 'Client record • Start chat',
                            'unread_count' => 0,
                            'bank_id' => $client->bank_id,
                            'updated_at' => optional($client->updated_at)->toIso8601String(),
                            'created_at' => optional($client->created_at)->toIso8601String(),
                            'client' => $client,
                            'agent' => $client->assignedTo,
                            'latest_message' => null,
                        ]);
                    }
                }
            }

            $merged = $sessions->concat($clientItems);

            return response()->json([
                'data' => $merged->values(),
                'current_page' => 1,
                'last_page' => 1,
                'total' => $merged->count(),
            ]);
        }

        $perPage = min((int) $request->get('per_page', 100), 500);

        return $query->paginate($perPage);
    }

    public function filters()
    {
        $user = $this->authorizeView();
        /** @var BankWabaResolver $resolver */
        $resolver = app(BankWabaResolver::class);

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
        $seenNumbers = [];

        foreach ($liveSenders as $s) {
            $pId = (string) ($s['phone_number_id'] ?? '');
            $num = $s['number'] ?? '';
            $lbl = $s['label'] ?? '';

            // Match against database WhatsappAccount
            $acc = $accounts->first(function ($a) use ($pId, $num, $resolver) {
                if ($pId && (string) $a->phone_number_id === $pId) return true;
                if ($num && $resolver->phonesMatch($a->display_phone_number, $num)) return true;
                return false;
            });

            $matchedBank = $resolver->resolveBankForSender($pId, $num, $acc?->name ?: $lbl);
            $bankId = $matchedBank?->id ?: $acc?->bank_id;
            $bankName = $matchedBank?->name ?: $acc?->bank?->name;

            $wabas[] = [
                'number' => $num,
                'label' => $acc?->name ?: $lbl,
                'default' => $s['default'] ?? false,
                'phone_number_id' => $pId,
                'bank_id' => $bankId,
                'bank_name' => $bankName,
            ];

            if ($pId) {
                $seenPhoneIds[] = $pId;
            }
            if ($num) {
                $seenNumbers[] = $num;
            }
        }

        // Include any database accounts not returned by live Meta service call
        foreach ($accounts as $acc) {
            $pId = (string) $acc->phone_number_id;
            if ($pId && !in_array($pId, $seenPhoneIds, true)) {
                $matchedBank = $resolver->resolveBankForSender($pId, $acc->display_phone_number, $acc->name);
                $bankId = $matchedBank?->id ?: $acc->bank_id;
                $bankName = $matchedBank?->name ?: $acc->bank?->name;

                $wabas[] = [
                    'number' => $acc->display_phone_number ?: $acc->phone_number_id,
                    'label' => $acc->name,
                    'default' => false,
                    'phone_number_id' => $acc->phone_number_id,
                    'bank_id' => $bankId,
                    'bank_name' => $bankName,
                ];
                $seenPhoneIds[] = $pId;
                if ($acc->display_phone_number) {
                    $seenNumbers[] = $acc->display_phone_number;
                }
            }
        }

        // Include any Bank primary WhatsApp number not yet present in $wabas
        $allBanks = \App\Models\Bank::with('whatsappAccount')->get();
        foreach ($allBanks as $b) {
            if (!empty($b->primary_whatsapp_number)) {
                $alreadySeen = false;
                foreach ($seenNumbers as $sn) {
                    if ($resolver->phonesMatch($sn, $b->primary_whatsapp_number)) {
                        $alreadySeen = true;
                        break;
                    }
                }

                if (!$alreadySeen) {
                    $phoneId = $b->whatsappAccount?->phone_number_id ?: $b->primary_whatsapp_number;
                    $wabas[] = [
                        'number' => $b->primary_whatsapp_number,
                        'label' => $b->name,
                        'default' => false,
                        'phone_number_id' => (string) $phoneId,
                        'bank_id' => $b->id,
                        'bank_name' => $b->name,
                    ];
                    $seenNumbers[] = $b->primary_whatsapp_number;
                }
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

    public function show(Request $request, ChatSession $session)
    {
        $user = $this->authorizeView();
        $this->authorizeSessionScope($user, $session);

        $session->load(['messages' => function ($q) {
            $q->orderBy('sent_at', 'asc');
        }, 'agent', 'client']);

        // Keep review-only roles read-only by skipping unread-count mutation.
        if (!$user->isReadOnlyRole() && !$request->boolean('peek')) {
            $session->update(['unread_count' => 0]);
        }

        return $this->appendCampaignMessages($session);
    }

    public function markUnread(ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);

        // Only change chats that are currently read, so repeated clicks cannot
        // replace a real unread message count that arrived in the meantime.
        ChatSession::query()
            ->whereKey($session->id)
            ->where(function ($query) {
                $query->where('unread_count', 0)->orWhereNull('unread_count');
            })
            ->update(['unread_count' => 1]);

        return response()->json($session->refresh());
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
            'delivery_status' => $session->platform === 'whatsapp' ? 'pending' : null,
        ]);

        $session->update([
            'last_message' => $content,
            'updated_at'   => now(),
        ]);

        // Keep Meta's message ID so status webhooks can update this exact chat message.
        if ($session->platform === 'whatsapp') {
            $result = null;
            try {
                $result = $mediaUrl
                    ? $this->sendWhatsappMediaReply($session, $mediaType, $mediaUrl, $data['content'] ?? null, $originalFilename)
                    : $this->sendWhatsappReply($session, $content);
            } catch (\Throwable $e) {
                // A timeout or Meta 5xx may happen after Meta accepted the POST.
                // Without a message ID, its outcome is unknown, not failed.
                $outcomeUnknown = $e instanceof \Illuminate\Http\Client\ConnectionException
                    || preg_match('/^Meta API error \[5\d\d\](?:\s|:)/', $e->getMessage()) === 1;
                [$errorCode, $errorMessage] = $this->chatDeliveryErrorFromException($e);
                $message->update([
                    'delivery_status' => $outcomeUnknown ? 'unknown' : 'failed',
                    'delivery_status_at' => now(),
                    'delivery_error_code' => $errorCode,
                    'delivery_error_message' => $errorMessage,
                ]);
                Log::error('Failed to send WhatsApp chat reply', [
                    'session_id' => $session->id,
                    'chat_message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($result !== null) {
                $providerMessageId = $result['message_id'] ?? $result['sid'] ?? null;
                $message->update([
                    'provider_message_id' => $providerMessageId,
                    'delivery_status' => $providerMessageId ? 'accepted' : 'unknown',
                    'delivery_status_at' => now(),
                    'delivery_error_code' => null,
                    'delivery_error_message' => null,
                ]);
            }
        }

        return response()->json($message, 201);
    }

    public function storeTemplateMessage(Request $request, ChatSession $session)
    {
        $this->authorizeManage();
        $this->authorizeSessionScope(Auth::user(), $session);

        $data = $request->validate([
            'template_id' => ['required', 'string', 'max:255'],
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['nullable', 'string', 'max:1024'],
        ]);

        if (strtolower((string) $session->platform) !== 'whatsapp') {
            return response()->json(['message' => 'Templates can only be sent to WhatsApp chats.'], 422);
        }

        $settings = \App\Models\SystemSetting::first();
        if ($settings && $settings->live_chat_locked) {
            return response()->json([
                'message' => $settings->live_chat_locked_message ?: 'Live chat is temporarily disabled.'
            ], 403);
        }

        $template = WhatsappTemplateCache::query()
            ->where('sid', $data['template_id'])
            ->first();

        if (!$template || strtoupper((string) $template->status) !== 'APPROVED') {
            return response()->json(['message' => 'The selected WhatsApp template is unavailable or is not approved.'], 422);
        }

        $submittedVariables = $data['variables'] ?? [];
        $variables = [];
        foreach (array_keys($template->variables ?? []) as $key) {
            $value = trim((string) ($submittedVariables[$key] ?? ''));
            if ($value === '') {
                return response()->json([
                    'message' => "A value is required for template variable {$key}.",
                    'errors' => ["variables.{$key}" => ["A value is required for template variable {$key}."]],
                ], 422);
            }
            $variables[$key] = $value;
        }

        $to = $session->client?->phone ?: $session->phone;
        if (!$to) {
            return response()->json(['message' => 'No phone number is available for this chat.'], 422);
        }

        $this->enforceMetaPermissionHealthForProduction('Live chat WhatsApp template sending');

        $content = $this->renderTemplateMessage($template, $variables);
        $message = $session->messages()->create([
            'sender' => 'agent',
            'content' => $content,
            'is_template' => true,
            'sent_at' => now(),
            'delivery_status' => 'pending',
        ]);

        $session->update([
            'last_message' => $content,
            'updated_at' => now(),
        ]);

        try {
            $senderContext = null;
            if (!$session->waba_phone_number_id && method_exists($this->whatsApp, 'resolveSenderForClient')) {
                $senderContext = $this->whatsApp->resolveSenderForClient($session->client);
            }

            $result = $this->whatsApp->sendTemplateFromSubjectMessage(
                $to,
                $template->sid,
                '',
                '',
                $variables,
                $session->waba_phone_number_id ?: ($senderContext['display_phone_number'] ?? null)
            );

            $providerMessageId = $result['message_id'] ?? $result['sid'] ?? null;
            $message->update([
                'provider_message_id' => $providerMessageId,
                'delivery_status' => $providerMessageId ? 'accepted' : 'unknown',
                'delivery_status_at' => now(),
                'delivery_error_code' => null,
                'delivery_error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $outcomeUnknown = $e instanceof \Illuminate\Http\Client\ConnectionException
                || preg_match('/^Meta API error \[5\d\d\](?:\s|:)/', $e->getMessage()) === 1;
            [$errorCode, $errorMessage] = $this->chatDeliveryErrorFromException($e);
            $message->update([
                'delivery_status' => $outcomeUnknown ? 'unknown' : 'failed',
                'delivery_status_at' => now(),
                'delivery_error_code' => $errorCode,
                'delivery_error_message' => $errorMessage,
            ]);
            Log::error('Failed to send WhatsApp chat template', [
                'session_id' => $session->id,
                'chat_message_id' => $message->id,
                'template' => $template->sid,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json($message->fresh(), 201);
    }

    protected function chatDeliveryErrorFromException(\Throwable $exception): array
    {
        $message = trim($exception->getMessage());
        $code = null;

        if (preg_match('/Meta code\s+(\d+)/i', $message, $matches) === 1) {
            $code = $matches[1];
        } elseif (preg_match('/^Meta API error \[(\d+)\](?:\s|:)/', $message, $matches) === 1) {
            $code = 'HTTP ' . $matches[1];
        }

        return [$code, $message !== '' ? $message : 'WhatsApp rejected the message without an error description.'];
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
        $user = $this->authorizeView();

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'platform'  => ['sometimes', 'string', 'max:50'],
            'waba_number' => ['sometimes', 'string', 'max:255'],
            'source_chat_session_id' => ['sometimes', 'nullable', 'integer', 'exists:chat_sessions,id'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $this->authorizeClientScopeForUser($request->user(), $client, 'chat with');
        $platform = $data['platform'] ?? 'whatsapp';

        $attributes = [
            'client_name' => $client->name,
            'bank_id'     => $client->bank_id,
            'phone'       => $client->phone ?: $client->cell_phone,
            'status'      => 'active',
            'agent_id'    => Auth::id(),
            'unread_count'=> 0,
        ];

        if (!empty($data['waba_number'])) {
            $attributes['waba_phone_number_id'] = $data['waba_number'];
        }

        $session = null;
        if (!empty($data['source_chat_session_id'])) {
            $session = ChatSession::findOrFail($data['source_chat_session_id']);
            $this->authorizeSessionScope($request->user(), $session);

            if ($session->client_id && (int) $session->client_id !== (int) $client->id) {
                abort(422, 'This chat session is already linked to another client.');
            }

            $session->update($attributes + [
                'client_id' => $client->id,
                'platform' => $platform,
                'phone' => $session->phone ?: ($client->phone ?: $client->cell_phone),
            ]);
        }

        if (!$session) {
            $session = ChatSession::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'platform'  => $platform,
                ],
                $attributes
            );
        }

        if (empty($session->phone) && ($client->phone || $client->cell_phone)) {
            $session->update(['phone' => $client->phone ?: $client->cell_phone]);
        }

        if (!empty($data['waba_number']) && $session->waba_phone_number_id !== $data['waba_number']) {
            $session->update(['waba_phone_number_id' => $data['waba_number']]);
        }

        // Load messages ordered and reset unread count when fetched
        $session->load(['client.departments', 'client.bank', 'agent', 'messages' => function ($q) {
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
            $resolver = app(BankWabaResolver::class);
            if ($session->waba_phone_number_id) {
                $allowedWabaPhoneIds = $resolver->getAllowedWabaPhoneIdsForBanks($bankIds);
                $bankNumbers = $resolver->getPhoneNumbersForBanks($bankIds);
                $hasWabaMatch = in_array((string) $session->waba_phone_number_id, $allowedWabaPhoneIds, true)
                    || in_array((string) $session->waba_phone_number_id, $bankNumbers, true);
            }

            if (!$hasBankMatch && !$hasWabaMatch) {
                abort(403, 'You do not have permission to act on this chat session.');
            }

            // Auto-repair missing session bank_id
            if (empty($session->bank_id)) {
                $resolvedId = $sessionBankId;
                if (!$resolvedId && $session->waba_phone_number_id) {
                    $matchedBank = $resolver->resolveBankForSender($session->waba_phone_number_id);
                    $resolvedId = $matchedBank?->id;
                }
                if ($resolvedId) {
                    $session->update(['bank_id' => $resolvedId]);
                }
            }
        }

        if (!$user->canAccessAllBanks() && $session->client) {
            $session->client->loadMissing('departments:id');
            $deptIds = $session->client->departments->pluck('id')->all();
            if (!empty($deptIds) && !$user->canAccessAnyDepartment($deptIds)) {
                abort(403, 'You do not have permission to act on this chat session.');
            }
        }

        if ($user->isPortfolioScoped() && $session->client && (int) $session->client->assigned_to_id !== (int) $user->id) {
            abort(403, 'You are not allowed to access this chat session.');
        }
    }

    protected function authorizeClientScope($user, Client $client): void
    {
        $this->authorizeClientScopeForUser($user, $client, 'access');
    }

    protected function sendWhatsappReply(ChatSession $session, string $body): array
    {
        $client = $session->client;
        $to = $client?->phone ?: $session->phone;
        if (!$to) {
            throw new \RuntimeException('No phone number on chat session.');
        }

        Log::info('Chat WhatsApp reply attempt', [
            'session_id' => $session->id,
            'client_id' => $session->client_id,
            'to' => $to,
            'sender_reference' => $session->waba_phone_number_id,
            'body_length' => mb_strlen($body),
        ]);

        $overrideFrom = $this->resolveChatSenderReference($session);

        return $this->whatsApp->sendPlainWhatsapp($to, $body, $overrideFrom);
    }

    protected function renderTemplateMessage(WhatsappTemplateCache $template, array $variables): string
    {
        $render = static function (?string $text, string $prefix) use ($variables): string {
            return preg_replace_callback(
                '/{{(\d+)}}/',
                static fn (array $match) => $variables["{$prefix}_{$match[1]}"] ?? $match[0],
                (string) $text
            );
        };

        $parts = array_filter([
            $render($template->header_text, 'header'),
            $render($template->body_preview, 'body'),
            trim((string) $template->footer_text),
        ], static fn (string $part) => trim($part) !== '');

        return $parts
            ? implode("\n", $parts)
            : 'Template: ' . $template->friendly_name;
    }

    protected function sendWhatsappMediaReply(ChatSession $session, string $mediaType, string $mediaUrl, ?string $caption = null, ?string $filename = null): array
    {
        $client = $session->client;
        $to = $client?->phone ?: $session->phone;
        if (!$to) {
            throw new \RuntimeException('No phone number on chat session.');
        }

        Log::info('Chat WhatsApp media reply attempt', [
            'session_id' => $session->id,
            'client_id' => $session->client_id,
            'to' => $to,
            'sender_reference' => $session->waba_phone_number_id,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
        ]);

        $overrideFrom = $this->resolveChatSenderReference($session);

        if (method_exists($this->whatsApp, 'sendMediaWhatsapp')) {
            return $this->whatsApp->sendMediaWhatsapp($to, $mediaType, $mediaUrl, $caption, $filename, $overrideFrom);
        }

        return $this->whatsApp->sendPlainWhatsapp($to, $caption ?: "[Attachment: {$mediaUrl}]", $overrideFrom);
    }

    /**
     * Replies must leave through the same WhatsApp number that received the
     * customer's message. The open 24-hour conversation window belongs to
     * that sender/customer pair, not merely to the client's bank.
     */
    protected function resolveChatSenderReference(ChatSession $session): ?string
    {
        $sessionSender = trim((string) $session->waba_phone_number_id);
        if ($sessionSender !== '') {
            return $sessionSender;
        }

        $senderContext = method_exists($this->whatsApp, 'resolveSenderForClient')
            ? $this->whatsApp->resolveSenderForClient($session->client)
            : null;

        return $senderContext['display_phone_number'] ?? null;
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
