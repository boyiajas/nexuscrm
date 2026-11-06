<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\WhatsappAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WhatsAppResponseTimeService
{
    /**
     * Compute full response time analytics and drill-down metrics.
     */
    public function getAnalytics(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $bankId = null,
        ?string $wabaPhoneNumberId = null,
        ?int $agentId = null,
        ?int $clientId = null,
        ?int $chatSessionId = null,
        ?User $requestingUser = null,
        int $slaThresholdMinutes = 15
    ): array {
        $slaThresholdSeconds = max(1, $slaThresholdMinutes) * 60;

        // 1. Load registered WABA numbers metadata
        $numbersMetadata = $this->resolveNumbersMetadata();

        // 2. Query chat sessions with their messages
        $sessionsQuery = ChatSession::with([
            'client:id,name,phone,cell_phone,bank_id',
            'bank:id,name',
            'agent:id,name,email,role,department_id',
            'messages' => function ($mQuery) use ($startDate, $endDate) {
                $mQuery->select([
                    'id',
                    'chat_session_id',
                    'user_id',
                    'sender',
                    'content',
                    'is_template',
                    'sent_at',
                    'created_at',
                ])->orderBy('sent_at', 'asc')->orderBy('id', 'asc');
            },
        ])->where('platform', 'whatsapp');

        // Apply bank access permissions
        if ($requestingUser && !$requestingUser->isSuperAdmin() && !$requestingUser->canAccessAllBanks()) {
            $accessibleBankIds = $requestingUser->accessibleBankIds() ?? [];
            if (empty($accessibleBankIds)) {
                $sessionsQuery->whereRaw('1 = 0');
            } else {
                $sessionsQuery->whereIn('bank_id', $accessibleBankIds);
            }
        }

        // Apply filters
        if ($bankId && $bankId !== -1) {
            $sessionsQuery->where('bank_id', $bankId);
        }
        if ($wabaPhoneNumberId) {
            $sessionsQuery->where('waba_phone_number_id', $wabaPhoneNumberId);
        }
        if ($agentId) {
            $sessionsQuery->where(function ($q) use ($agentId) {
                $q->where('agent_id', $agentId)
                  ->orWhereHas('messages', fn ($mq) => $mq->where('user_id', $agentId));
            });
        }
        if ($clientId) {
            $sessionsQuery->where('client_id', $clientId);
        }
        if ($chatSessionId) {
            $sessionsQuery->where('id', $chatSessionId);
        }

        $sessions = $sessionsQuery->get();

        // 3. Process each session to extract client-to-agent turns
        $allTurns = collect();
        $turnsBySession = [];

        foreach ($sessions as $session) {
            $sessionTurns = $this->extractSessionTurns($session, $startDate, $endDate);
            $turnsBySession[$session->id] = $sessionTurns;
            foreach ($sessionTurns as $turn) {
                $allTurns->push($turn);
            }
        }

        // 4. Compute overall KPI metrics
        $answeredTurns = $allTurns->where('status', 'answered');
        $pendingTurns = $allTurns->where('status', 'pending');

        $overallKpis = $this->calculateMetricsSummary($answeredTurns, $pendingTurns, $slaThresholdSeconds);

        // 5. Aggregate by WhatsApp Phone Number
        $numbersBreakdown = $this->aggregateByNumber($allTurns, $numbersMetadata, $slaThresholdSeconds);

        // 6. Aggregate by User / Agent
        $agentsBreakdown = $this->aggregateByAgent($allTurns, $slaThresholdSeconds);

        // 7. Aggregate by Client
        $clientsBreakdown = $this->aggregateByClient($allTurns, $slaThresholdSeconds);

        return [
            'summary' => $overallKpis,
            'sla_threshold_minutes' => $slaThresholdMinutes,
            'numbers' => $numbersBreakdown,
            'agents' => $agentsBreakdown,
            'clients' => $clientsBreakdown,
            'total_sessions_analyzed' => $sessions->count(),
            'total_turns_analyzed' => $allTurns->count(),
        ];
    }

    /**
     * Get detailed turn-by-turn audit for a specific chat session.
     */
    public function getSessionTurnAudit(int $chatSessionId, ?User $requestingUser = null, int $slaThresholdMinutes = 15): ?array
    {
        $session = ChatSession::with([
            'client:id,name,phone,cell_phone,bank_id',
            'bank:id,name',
            'agent:id,name,email,role,department_id',
            'messages' => function ($mQuery) {
                $mQuery->with('user:id,name,email,role')
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc');
            },
        ])->find($chatSessionId);

        if (!$session) {
            return null;
        }

        if ($requestingUser && !$requestingUser->isSuperAdmin() && !$requestingUser->canAccessAllBanks()) {
            $accessible = $requestingUser->accessibleBankIds() ?? [];
            if (!in_array((int) $session->bank_id, $accessible, true)) {
                return null;
            }
        }

        $turns = $this->extractSessionTurns($session);
        $slaThresholdSeconds = max(1, $slaThresholdMinutes) * 60;
        $answered = collect($turns)->where('status', 'answered');
        $pending = collect($turns)->where('status', 'pending');

        return [
            'session' => [
                'id' => $session->id,
                'client_id' => $session->client_id,
                'client_name' => $session->client_name ?: ($session->client?->name ?? 'Unknown Client'),
                'phone' => $session->phone ?: ($session->client?->phone ?? '—'),
                'bank_id' => $session->bank_id,
                'bank_name' => $session->bank?->name ?? '—',
                'waba_phone_number_id' => $session->waba_phone_number_id,
                'assigned_agent_id' => $session->agent_id,
                'assigned_agent_name' => $session->agent?->name ?? 'Unassigned',
            ],
            'summary' => $this->calculateMetricsSummary($answered, $pending, $slaThresholdSeconds),
            'turns' => array_values($turns),
        ];
    }

    /**
     * Extract interaction turns (client inbound -> agent response) from a chat session.
     */
    protected function extractSessionTurns(ChatSession $session, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $messages = $session->messages;
        if ($messages->isEmpty()) {
            return [];
        }

        $turns = [];
        $turnIndex = 1;

        /** @var ChatMessage|null $pendingClientMessage */
        $pendingClientMessage = null;
        /** @var Carbon|null $firstClientSentAt */
        $firstClientSentAt = null;

        foreach ($messages as $message) {
            $sender = strtolower(trim((string) $message->sender));
            $messageTime = $message->sent_at ?: $message->created_at;

            if ($sender === 'client' || $sender === 'customer' || $sender === 'user') {
                if ($firstClientSentAt === null) {
                    $firstClientSentAt = $messageTime ? Carbon::parse($messageTime) : now();
                    $pendingClientMessage = $message;
                }
            } elseif ($sender === 'agent' || $sender === 'admin' || $sender === 'support') {
                if ($pendingClientMessage !== null) {
                    $agentSentAt = $messageTime ? Carbon::parse($messageTime) : now();

                    // Check if inbound message falls within requested date range
                    $inDateRange = true;
                    if ($startDate && $firstClientSentAt->lt($startDate)) {
                        $inDateRange = false;
                    }
                    if ($endDate && $firstClientSentAt->gt($endDate)) {
                        $inDateRange = false;
                    }

                    if ($inDateRange) {
                        $durationSeconds = max(0, $agentSentAt->getTimestamp() - $firstClientSentAt->getTimestamp());
                        $replyingUserId = $message->user_id ?: $session->agent_id;
                        $replyingUserName = $message->user?->name
                            ?: ($session->agent?->name ?? 'Internal Agent');

                        $turns[] = [
                            'turn_index' => $turnIndex++,
                            'session_id' => $session->id,
                            'client_id' => $session->client_id,
                            'client_name' => $session->client_name ?: ($session->client?->name ?? 'Unknown Client'),
                            'client_phone' => $session->phone ?: ($session->client?->phone ?? '—'),
                            'bank_id' => $session->bank_id,
                            'bank_name' => $session->bank?->name ?? '—',
                            'waba_phone_number_id' => $session->waba_phone_number_id ?: 'default',
                            'user_id' => $replyingUserId,
                            'user_name' => $replyingUserName,
                            'inbound_message_id' => $pendingClientMessage->id,
                            'inbound_content' => $pendingClientMessage->content,
                            'inbound_at' => $firstClientSentAt->toIso8601String(),
                            'inbound_formatted' => $firstClientSentAt->format('Y-m-d H:i:s'),
                            'reply_message_id' => $message->id,
                            'reply_content' => $message->content,
                            'reply_at' => $agentSentAt->toIso8601String(),
                            'reply_formatted' => $agentSentAt->format('Y-m-d H:i:s'),
                            'duration_seconds' => $durationSeconds,
                            'duration_human' => $this->formatDuration($durationSeconds),
                            'status' => 'answered',
                        ];
                    }

                    $pendingClientMessage = null;
                    $firstClientSentAt = null;
                }
            }
        }

        // If an inbound message is still pending an agent response
        if ($pendingClientMessage !== null && $firstClientSentAt !== null) {
            $inDateRange = true;
            if ($startDate && $firstClientSentAt->lt($startDate)) {
                $inDateRange = false;
            }
            if ($endDate && $firstClientSentAt->gt($endDate)) {
                $inDateRange = false;
            }

            if ($inDateRange) {
                $waitSeconds = max(0, now()->getTimestamp() - $firstClientSentAt->getTimestamp());
                $turns[] = [
                    'turn_index' => $turnIndex++,
                    'session_id' => $session->id,
                    'client_id' => $session->client_id,
                    'client_name' => $session->client_name ?: ($session->client?->name ?? 'Unknown Client'),
                    'client_phone' => $session->phone ?: ($session->client?->phone ?? '—'),
                    'bank_id' => $session->bank_id,
                    'bank_name' => $session->bank?->name ?? '—',
                    'waba_phone_number_id' => $session->waba_phone_number_id ?: 'default',
                    'user_id' => $session->agent_id,
                    'user_name' => $session->agent?->name ?? 'Unassigned',
                    'inbound_message_id' => $pendingClientMessage->id,
                    'inbound_content' => $pendingClientMessage->content,
                    'inbound_at' => $firstClientSentAt->toIso8601String(),
                    'inbound_formatted' => $firstClientSentAt->format('Y-m-d H:i:s'),
                    'reply_message_id' => null,
                    'reply_content' => null,
                    'reply_at' => null,
                    'reply_formatted' => null,
                    'duration_seconds' => null,
                    'duration_human' => null,
                    'wait_seconds' => $waitSeconds,
                    'wait_human' => $this->formatDuration($waitSeconds),
                    'status' => 'pending',
                ];
            }
        }

        return $turns;
    }

    /**
     * Aggregate metrics per WhatsApp Phone Number.
     */
    protected function aggregateByNumber(Collection $allTurns, array $numbersMetadata, int $slaThresholdSeconds): array
    {
        $grouped = $allTurns->groupBy('waba_phone_number_id');

        // Ensure all registered WABA numbers appear in the report even if 0 turns occurred
        foreach ($numbersMetadata as $phoneId => $meta) {
            if (!$grouped->has((string) $phoneId)) {
                $grouped->put((string) $phoneId, collect());
            }
        }

        $result = [];
        foreach ($grouped as $phoneId => $turns) {
            $phoneIdStr = (string) $phoneId;
            $meta = $numbersMetadata[$phoneIdStr] ?? [
                'phone_number_id' => $phoneIdStr,
                'display_phone_number' => $phoneIdStr === 'default' ? 'Default Sender' : $phoneIdStr,
                'verified_name' => 'WhatsApp Number',
            ];

            $answered = $turns->where('status', 'answered');
            $pending = $turns->where('status', 'pending');
            $summary = $this->calculateMetricsSummary($answered, $pending, $slaThresholdSeconds);

            $activeAgentsCount = $answered->pluck('user_id')->filter()->unique()->count();

            $result[] = array_merge([
                'phone_number_id' => $phoneIdStr,
                'display_phone_number' => $meta['display_phone_number'] ?? $phoneIdStr,
                'verified_name' => $meta['verified_name'] ?? 'WhatsApp Number',
                'active_agents_count' => $activeAgentsCount,
            ], $summary);
        }

        usort($result, function ($a, $b) {
            return ($b['total_inbound'] <=> $a['total_inbound']) ?: ($b['answered_count'] <=> $a['answered_count']);
        });

        return $result;
    }

    /**
     * Aggregate metrics per User / Agent.
     */
    protected function aggregateByAgent(Collection $allTurns, int $slaThresholdSeconds): array
    {
        $grouped = $allTurns->groupBy(function ($turn) {
            return (string) ($turn['user_id'] ?: 'unassigned');
        });

        $userIds = $grouped->keys()->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all();
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email', 'role', 'department_id'])->keyBy('id');

        $result = [];
        foreach ($grouped as $agentKey => $turns) {
            $isUnassigned = $agentKey === 'unassigned';
            $user = !$isUnassigned ? $users->get((int) $agentKey) : null;

            $answered = $turns->where('status', 'answered');
            $pending = $turns->where('status', 'pending');
            $summary = $this->calculateMetricsSummary($answered, $pending, $slaThresholdSeconds);

            $names = preg_split('/\s+/', trim((string) ($user?->name ?? 'Unassigned'))) ?: [];
            $initials = strtoupper(substr($names[0] ?? 'U', 0, 1) . substr($names[1] ?? '', 0, 1));

            $result[] = array_merge([
                'user_id' => !$isUnassigned ? (int) $agentKey : null,
                'name' => $user?->name ?? ($isUnassigned ? 'Unassigned Inquiries' : 'Agent #' . $agentKey),
                'email' => $user?->email ?? '—',
                'role' => $user?->role ? ucwords(strtolower(str_replace('_', ' ', $user->role))) : ($isUnassigned ? 'System Queue' : 'Agent'),
                'initials' => $initials,
                'clients_count' => $turns->pluck('client_id')->filter()->unique()->count(),
            ], $summary);
        }

        usort($result, function ($a, $b) {
            return ($b['total_inbound'] <=> $a['total_inbound']) ?: ($b['answered_count'] <=> $a['answered_count']);
        });

        return $result;
    }

    /**
     * Aggregate metrics per Client.
     */
    protected function aggregateByClient(Collection $allTurns, int $slaThresholdSeconds): array
    {
        $grouped = $allTurns->groupBy(function ($turn) {
            return (string) ($turn['client_id'] ?: 'phone_' . ($turn['client_phone'] ?: 'unknown'));
        });

        $result = [];
        foreach ($grouped as $clientKey => $turns) {
            $firstTurn = $turns->first();
            $answered = $turns->where('status', 'answered');
            $pending = $turns->where('status', 'pending');
            $summary = $this->calculateMetricsSummary($answered, $pending, $slaThresholdSeconds);

            $lastTurn = $turns->sortByDesc(fn ($t) => $t['inbound_at'])->first();

            $result[] = array_merge([
                'client_id' => $firstTurn['client_id'] ?? null,
                'session_id' => $firstTurn['session_id'] ?? null,
                'client_name' => $firstTurn['client_name'] ?? 'Unknown Client',
                'client_phone' => $firstTurn['client_phone'] ?? '—',
                'bank_id' => $firstTurn['bank_id'] ?? null,
                'bank_name' => $firstTurn['bank_name'] ?? '—',
                'waba_phone_number_id' => $firstTurn['waba_phone_number_id'] ?? 'default',
                'last_agent_name' => $lastTurn['user_name'] ?? '—',
                'last_inbound_formatted' => $lastTurn['inbound_formatted'] ?? '—',
                'last_reply_formatted' => $lastTurn['reply_formatted'] ?? 'Pending',
                'has_pending' => $pending->isNotEmpty(),
            ], $summary);
        }

        usort($result, function ($a, $b) {
            // Pending first, then by total inbound
            if ($a['has_pending'] !== $b['has_pending']) {
                return $a['has_pending'] ? -1 : 1;
            }
            return $b['total_inbound'] <=> $a['total_inbound'];
        });

        return $result;
    }

    /**
     * Compute summary stats (avg, median, min, max, SLA) for a set of answered and pending turns.
     */
    protected function calculateMetricsSummary(Collection $answeredTurns, Collection $pendingTurns, int $slaThresholdSeconds): array
    {
        $answeredCount = $answeredTurns->count();
        $pendingCount = $pendingTurns->count();
        $totalInbound = $answeredCount + $pendingCount;

        $durations = $answeredTurns->pluck('duration_seconds')->filter(fn ($v) => $v !== null)->values()->all();
        sort($durations);

        $avgSeconds = null;
        $medianSeconds = null;
        $minSeconds = null;
        $maxSeconds = null;
        $slaCompliantCount = 0;
        $slaRate = 0.0;

        if ($answeredCount > 0) {
            $avgSeconds = (int) round(array_sum($durations) / $answeredCount);
            $minSeconds = (int) $durations[0];
            $maxSeconds = (int) $durations[$answeredCount - 1];

            $middle = (int) floor($answeredCount / 2);
            if ($answeredCount % 2 === 0) {
                $medianSeconds = (int) round(($durations[$middle - 1] + $durations[$middle]) / 2);
            } else {
                $medianSeconds = (int) $durations[$middle];
            }

            foreach ($durations as $d) {
                if ($d <= $slaThresholdSeconds) {
                    $slaCompliantCount++;
                }
            }
            $slaRate = round(($slaCompliantCount / $answeredCount) * 100, 1);
        }

        $pendingWaitDurations = $pendingTurns->pluck('wait_seconds')->filter(fn ($v) => $v !== null)->values()->all();
        $longestWaitSeconds = !empty($pendingWaitDurations) ? max($pendingWaitDurations) : null;

        return [
            'total_inbound' => $totalInbound,
            'answered_count' => $answeredCount,
            'pending_count' => $pendingCount,
            'avg_response_seconds' => $avgSeconds,
            'avg_response_human' => $avgSeconds !== null ? $this->formatDuration($avgSeconds) : '—',
            'median_response_seconds' => $medianSeconds,
            'median_response_human' => $medianSeconds !== null ? $this->formatDuration($medianSeconds) : '—',
            'fastest_response_seconds' => $minSeconds,
            'fastest_response_human' => $minSeconds !== null ? $this->formatDuration($minSeconds) : '—',
            'slowest_response_seconds' => $maxSeconds,
            'slowest_response_human' => $maxSeconds !== null ? $this->formatDuration($maxSeconds) : '—',
            'sla_compliant_count' => $slaCompliantCount,
            'sla_compliance_rate' => $slaRate,
            'sla_compliance_formatted' => $answeredCount > 0 ? $slaRate . '%' : '—',
            'longest_wait_seconds' => $longestWaitSeconds,
            'longest_wait_human' => $longestWaitSeconds !== null ? $this->formatDuration($longestWaitSeconds) : '—',
        ];
    }

    /**
     * Resolve metadata for all provisioned WABA phone numbers.
     */
    protected function resolveNumbersMetadata(): array
    {
        $metadata = [];

        // 1. From local WhatsappAccount table
        $accounts = WhatsappAccount::query()->get();
        foreach ($accounts as $acc) {
            if (!empty($acc->phone_number_id)) {
                $metadata[(string) $acc->phone_number_id] = [
                    'phone_number_id' => (string) $acc->phone_number_id,
                    'display_phone_number' => $acc->display_phone_number ?: $acc->name,
                    'verified_name' => $acc->name ?: 'WhatsApp Profile',
                ];
            }
        }

        // 2. From Meta WhatsApp Service cache if available
        try {
            $service = app(MetaWhatsAppService::class);
            if (method_exists($service, 'getPhoneNumbers')) {
                $liveNumbers = $service->getPhoneNumbers();
                foreach ($liveNumbers as $num) {
                    if (!empty($num['id'])) {
                        $metadata[(string) $num['id']] = [
                            'phone_number_id' => (string) $num['id'],
                            'display_phone_number' => $num['display_phone_number'] ?? ($metadata[(string) $num['id']]['display_phone_number'] ?? (string) $num['id']),
                            'verified_name' => $num['verified_name'] ?? ($metadata[(string) $num['id']]['verified_name'] ?? 'Verified Number'),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fall back to DB metadata
        }

        return $metadata;
    }

    /**
     * Convert seconds to a human-readable duration format (e.g. "3m 15s" or "1h 20m").
     */
    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            $m = (int) floor($seconds / 60);
            $s = $seconds % 60;
            return $s > 0 ? "{$m}m {$s}s" : "{$m}m";
        }
        if ($seconds < 86400) {
            $h = (int) floor($seconds / 3600);
            $m = (int) floor(($seconds % 3600) / 60);
            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }

        $d = (int) floor($seconds / 86400);
        $h = (int) floor(($seconds % 86400) / 3600);
        return $h > 0 ? "{$d}d {$h}h" : "{$d}d";
    }
}
