<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $query = ChatSession::with('client', 'agent')
            ->orderByDesc('updated_at');

        if ($status = $request->get('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        return $query->paginate(20);
    }

    public function show(ChatSession $session)
    {
        $session->load(['client', 'agent', 'messages' => function ($q) {
            $q->orderBy('created_at');
        }]);

        // Reset unread count when agent opens session
        $session->update(['unread_count' => 0]);

        return $session;
    }

    public function storeMessage(Request $request, ChatSession $session)
    {
        $data = $request->validate([
            'content'     => ['required', 'string'],
            'is_template' => ['sometimes', 'boolean'],
        ]);

        $message = $session->messages()->create([
            'sender'      => 'agent', // future: use 'system' or 'user' as needed
            'content'     => $data['content'],
            'is_template' => $data['is_template'] ?? false,
            'sent_at'     => now(),
        ]);

        $session->update([
            'last_message' => $data['content'],
        ]);

        return response()->json($message, 201);
    }

    // For future webhook/Twilio inbound messages:
    public function receiveFromClient(Request $request)
    {
        // TODO: handle Twilio webhook, find/create ChatSession,
        // create ChatMessage with sender='user', increment unread_count, etc.
    }
}
