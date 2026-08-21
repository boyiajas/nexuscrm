<?php

namespace App\Http\Controllers\Api;

use App\Concerns\EnforcesMetaPermissionHealth;
use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        if (!$user->canAccessAllBanks() && !empty($user->resolvedBankIds())) {
            $bankIds = $user->resolvedBankIds();
            $query->where(function ($q) use ($bankIds) {
                $q->whereIn('bank_id', $bankIds)
                  ->orWhereHas('client', function ($cq) use ($bankIds) {
                      $cq->whereIn('bank_id', $bankIds);
                  })
                  ->orWhereNull('bank_id');
            });
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

        $data = $request->validate([
            'content'     => ['required', 'string'],
            'is_template' => ['sometimes', 'boolean'],
        ]);

        if ($session->platform === 'whatsapp') {
            $this->enforceMetaPermissionHealthForProduction('Live chat WhatsApp sending');
        }

        $message = $session->messages()->create([
            'sender'      => 'agent', // future: use 'system' or 'user' as needed
            'content'     => $data['content'],
            'is_template' => $data['is_template'] ?? false,
            'sent_at'     => now(),
        ]);

        $session->update([
            'last_message' => $data['content'],
            'updated_at'   => now(),
        ]);

        // Try sending outbound WhatsApp for live chat sessions
        if ($session->platform === 'whatsapp') {
            $this->sendWhatsappReply($session, $data['content']);
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
        ]);

        $client = Client::findOrFail($data['client_id']);
        $this->authorizeClientScope($request->user(), $client);
        $platform = $data['platform'] ?? 'whatsapp';

        $session = ChatSession::firstOrCreate(
            [
                'client_id' => $client->id,
                'platform'  => $platform,
            ],
            [
                'client_name' => $client->name,
                'bank_id' => $client->bank_id,
                'status'      => 'active',
                'agent_id'    => Auth::id(),
                'unread_count'=> 0,
            ]
        );

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

        $chatMessages = $session->messages;

        $batchService = app(\App\Services\WhatsAppBatchService::class);
        $campaignMessages = \App\Models\CampaignWhatsappRecipient::with(['message.campaign', 'message.autoReplies', 'client'])
            ->where('client_id', $session->client_id)
            ->get();

        $mappedCampaignMessages = $campaignMessages->map(function ($recipient) use ($session, $batchService) {
            $content = $recipient->message ? $recipient->message->preview_body : 'Campaign Message';
            $sentAt = $recipient->delivered_at ?? $recipient->queued_at ?? $recipient->created_at;

            if ($recipient->message && $recipient->client && $recipient->message->campaign) {
                $values = $batchService->resolveTemplateVariableValues(
                    $recipient->message->template_variables ?? [],
                    $recipient->client,
                    $recipient->message->campaign
                );

                foreach ($values as $key => $val) {
                    if (str_starts_with($key, 'body_')) {
                        $index = str_replace('body_', '', $key);
                        $content = str_replace('{{' . $index . '}}', $val, $content);
                    }
                }
            }

            $buttons = [];
            if ($recipient->message && $recipient->message->autoReplies && $recipient->message->autoReplies->isNotEmpty()) {
                foreach ($recipient->message->autoReplies as $reply) {
                    $buttons[] = "🔘 " . $reply->trigger_keyword;
                }
            }

            if (!empty($buttons)) {
                $content .= "\n\n" . implode("  ", $buttons);
            }

            $msg = new ChatMessage([
                'chat_session_id' => $session->id,
                'sender' => 'agent',
                'content' => "📄 [Template Sent]\n" . $content,
                'is_template' => true,
            ]);
            
            // Force the ID and timestamps
            $msg->setAttribute('id', 'camp_' . $recipient->id);
            $msg->setAttribute('sent_at', $sentAt);
            $msg->setAttribute('created_at', $recipient->created_at);

            return $msg;
        });

        $allMessages = $chatMessages->concat($mappedCampaignMessages)->sortBy('sent_at')->values();

        $session->setRelation('messages', $allMessages);

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
        if (!$user->canAccessAllBanks() && !empty($user->resolvedBankIds()) && !in_array((int) $session->bank_id, $user->resolvedBankIds(), true)) {
            abort(403, 'You do not have permission to act on this chat session.');
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
        $to = $session->client?->phone ?: $session->phone;
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
            $this->whatsApp->sendPlainWhatsapp($to, $body);
        } catch (\Throwable $e) {
            Log::error('Failed to send WhatsApp chat reply', [
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
