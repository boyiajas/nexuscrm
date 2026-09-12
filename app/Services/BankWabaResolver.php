<?php

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\Bank;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Log;

class BankWabaResolver
{
    public function __construct(protected ?WhatsAppServiceInterface $whatsApp = null)
    {
        if (!$this->whatsApp && app()->bound(WhatsAppServiceInterface::class)) {
            $this->whatsApp = app(WhatsAppServiceInterface::class);
        }
    }

    /**
     * Normalize a phone number to standard E.164 if possible.
     */
    public function normalizePhone(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        return MetaWhatsAppService::normalizePhoneNumber($raw) ?: ('+' . preg_replace('/\D+/', '', $raw));
    }

    /**
     * Get numeric digits only.
     */
    public function digitsOnly(?string $raw): string
    {
        return preg_replace('/\D+/', '', (string) $raw);
    }

    /**
     * Check if two phone strings represent the same number.
     */
    public function phonesMatch(?string $a, ?string $b): bool
    {
        if (!$a || !$b) {
            return false;
        }

        $normA = $this->normalizePhone($a);
        $normB = $this->normalizePhone($b);

        if ($normA && $normB && $normA === $normB) {
            return true;
        }

        $digitsA = $this->digitsOnly($a);
        $digitsB = $this->digitsOnly($b);

        if ($digitsA && $digitsB && $digitsA === $digitsB) {
            return true;
        }

        // Compare last 9 digits (handling leading zero vs country code differences)
        if (strlen($digitsA) >= 9 && strlen($digitsB) >= 9) {
            return substr($digitsA, -9) === substr($digitsB, -9);
        }

        return false;
    }

    /**
     * Resolve a Bank for a given WABA sender context (phone number ID, number, label).
     */
    public function resolveBankForSender(?string $phoneNumberId, ?string $number = null, ?string $label = null): ?Bank
    {
        $allBanks = Bank::all();
        if ($allBanks->isEmpty()) {
            return null;
        }

        $phoneNumberId = $phoneNumberId ? (string) $phoneNumberId : null;
        $acc = null;

        if ($phoneNumberId) {
            $acc = WhatsappAccount::where('phone_number_id', $phoneNumberId)->first();
        }

        if (!$acc && $number) {
            $accounts = WhatsappAccount::all();
            $acc = $accounts->first(fn ($a) => $this->phonesMatch($a->display_phone_number, $number));
        }

        // 1. Check account's assigned bank_id
        if ($acc && $acc->bank_id) {
            $bank = $allBanks->firstWhere('id', $acc->bank_id);
            if ($bank) {
                return $bank;
            }
        }

        // 2. Check if any bank has whatsapp_account_id pointing to this account
        if ($acc) {
            $bank = $allBanks->first(fn ($b) => (int) $b->whatsapp_account_id === (int) $acc->id);
            if ($bank) {
                if (empty($acc->bank_id)) {
                    $acc->update(['bank_id' => $bank->id]);
                }
                return $bank;
            }
        }

        // 3. Match against bank primary_whatsapp_number
        if ($number) {
            $bank = $allBanks->first(fn ($b) => !empty($b->primary_whatsapp_number) && $this->phonesMatch($b->primary_whatsapp_number, $number));
            if ($bank) {
                $this->linkAccountAndBank($acc, $bank);
                return $bank;
            }
        }

        // 4. Match against bank secondary_whatsapp_numbers
        if ($number) {
            $bank = $allBanks->first(function ($b) use ($number) {
                if (!empty($b->secondary_whatsapp_numbers) && is_array($b->secondary_whatsapp_numbers)) {
                    foreach ($b->secondary_whatsapp_numbers as $secNum) {
                        if ($this->phonesMatch($secNum, $number)) {
                            return true;
                        }
                    }
                }
                return false;
            });
            if ($bank) {
                $this->linkAccountAndBank($acc, $bank);
                return $bank;
            }
        }

        // 5. Match by label / verified_name or account name
        $nameToMatch = strtolower(trim($acc?->name ?: ($label ?? '')));
        if (!empty($nameToMatch)) {
            $bank = $allBanks->first(function ($b) use ($nameToMatch) {
                $bankName = strtolower(trim($b->name));
                $bankCode = strtolower(trim($b->code));
                return $bankName === $nameToMatch
                    || $bankCode === $nameToMatch
                    || str_contains($bankName, $nameToMatch)
                    || str_contains($nameToMatch, $bankName);
            });
            if ($bank) {
                $this->linkAccountAndBank($acc, $bank);
                return $bank;
            }
        }

        return null;
    }

    protected function linkAccountAndBank(?WhatsappAccount $acc, Bank $bank): void
    {
        if ($acc && empty($acc->bank_id)) {
            $acc->update(['bank_id' => $bank->id]);
        }
        if ($acc && empty($bank->whatsapp_account_id)) {
            $bank->update(['whatsapp_account_id' => $acc->id]);
        }
    }

    /**
     * Get all phone numbers (primary & secondary) for the given bank IDs.
     */
    public function getPhoneNumbersForBanks(array $bankIds): array
    {
        if (empty($bankIds)) {
            return [];
        }

        $banks = Bank::whereIn('id', $bankIds)->get();
        $numbers = [];

        foreach ($banks as $bank) {
            if (!empty($bank->primary_whatsapp_number)) {
                $numbers[] = $bank->primary_whatsapp_number;
                $norm = $this->normalizePhone($bank->primary_whatsapp_number);
                if ($norm) {
                    $numbers[] = $norm;
                }
            }
            if (!empty($bank->secondary_whatsapp_numbers) && is_array($bank->secondary_whatsapp_numbers)) {
                foreach ($bank->secondary_whatsapp_numbers as $sn) {
                    $numbers[] = $sn;
                    $norm = $this->normalizePhone($sn);
                    if ($norm) {
                        $numbers[] = $norm;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($numbers)));
    }

    /**
     * Get all allowed WABA phone number IDs for the given bank IDs.
     */
    public function getAllowedWabaPhoneIdsForBanks(array $bankIds): array
    {
        if (empty($bankIds)) {
            return [];
        }

        $phoneIds = [];

        // 1. Direct from whatsapp_accounts by bank_id
        $directIds = WhatsappAccount::whereIn('bank_id', $bankIds)
            ->pluck('phone_number_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();
        $phoneIds = array_merge($phoneIds, $directIds);

        // 2. From banks.whatsapp_account_id
        $linkedAccIds = Bank::whereIn('id', $bankIds)
            ->whereNotNull('whatsapp_account_id')
            ->pluck('whatsapp_account_id')
            ->filter()
            ->all();

        if (!empty($linkedAccIds)) {
            $linkedPhoneIds = WhatsappAccount::whereIn('id', $linkedAccIds)
                ->pluck('phone_number_id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->all();
            $phoneIds = array_merge($phoneIds, $linkedPhoneIds);
        }

        // 3. Match against all whatsapp_accounts by phone numbers
        $bankNumbers = $this->getPhoneNumbersForBanks($bankIds);
        if (!empty($bankNumbers)) {
            $accounts = WhatsappAccount::whereNotNull('phone_number_id')->get();
            foreach ($accounts as $acc) {
                foreach ($bankNumbers as $bNum) {
                    if ($this->phonesMatch($acc->display_phone_number, $bNum)) {
                        $phoneIds[] = (string) $acc->phone_number_id;
                        if (empty($acc->bank_id) && count($bankIds) === 1) {
                            $acc->update(['bank_id' => $bankIds[0]]);
                        }
                    }
                }
            }
        }

        // 4. Match against live WhatsApp senders if available
        if ($this->whatsApp) {
            try {
                $liveSenders = $this->whatsApp->listWhatsappSenders();
                foreach ($liveSenders as $sender) {
                    $sPhoneId = (string) ($sender['phone_number_id'] ?? '');
                    $sNum = $sender['number'] ?? '';
                    $sLabel = $sender['label'] ?? '';

                    $matchedBank = $this->resolveBankForSender($sPhoneId, $sNum, $sLabel);
                    if ($matchedBank && in_array((int) $matchedBank->id, $bankIds, true)) {
                        if ($sPhoneId) {
                            $phoneIds[] = $sPhoneId;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('BankWabaResolver: unable to query live senders: ' . $e->getMessage());
            }
        }

        return array_values(array_unique(array_filter($phoneIds)));
    }

    /**
     * Backfill bank_id on existing whatsapp_accounts and chat_sessions.
     */
    public function syncAndBackfill(): array
    {
        $linkedAccounts = 0;
        $linkedSessions = 0;

        $banks = Bank::all();
        $accounts = WhatsappAccount::all();

        // 1. Sync accounts to banks
        foreach ($accounts as $acc) {
            if (!$acc->bank_id) {
                $matchedBank = $this->resolveBankForSender($acc->phone_number_id, $acc->display_phone_number, $acc->name);
                if ($matchedBank) {
                    $acc->update(['bank_id' => $matchedBank->id]);
                    $linkedAccounts++;
                }
            }
        }

        // 2. If bank has whatsapp_account_id, ensure that account has bank_id
        foreach ($banks as $bank) {
            if ($bank->whatsapp_account_id) {
                $acc = WhatsappAccount::find($bank->whatsapp_account_id);
                if ($acc && $acc->bank_id !== $bank->id) {
                    $acc->update(['bank_id' => $bank->id]);
                    $linkedAccounts++;
                }
            }
        }

        // 3. Backfill chat_sessions.bank_id
        // a) From client
        $sessionsWithClient = ChatSession::whereNull('bank_id')
            ->whereNotNull('client_id')
            ->whereHas('client', fn ($q) => $q->whereNotNull('bank_id'))
            ->with('client:id,bank_id')
            ->get();

        foreach ($sessionsWithClient as $session) {
            if ($session->client?->bank_id) {
                $session->update(['bank_id' => $session->client->bank_id]);
                $linkedSessions++;
            }
        }

        // b) From waba_phone_number_id
        $sessionsWithWaba = ChatSession::whereNull('bank_id')
            ->whereNotNull('waba_phone_number_id')
            ->get();

        foreach ($sessionsWithWaba as $session) {
            $bank = $this->resolveBankForSender($session->waba_phone_number_id, null, null);
            if ($bank) {
                $session->update(['bank_id' => $bank->id]);
                $linkedSessions++;
            }
        }

        // c) From phone match against clients
        $sessionsWithPhone = ChatSession::whereNull('bank_id')
            ->whereNotNull('phone')
            ->get();

        foreach ($sessionsWithPhone as $session) {
            $client = Client::where('phone', $session->phone)
                ->whereNotNull('bank_id')
                ->first();
            if ($client) {
                $session->update([
                    'client_id' => $client->id,
                    'bank_id' => $client->bank_id,
                ]);
                $linkedSessions++;
            }
        }

        return [
            'linked_accounts' => $linkedAccounts,
            'linked_sessions' => $linkedSessions,
        ];
    }
}
