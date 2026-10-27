<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Client;
use Illuminate\Support\Collection;

class ClientInstitutionService
{
    /**
     * Cached lookup of bank names by ID.
     *
     * @var array<int, string>|null
     */
    protected ?array $bankLookup = null;

    /**
     * Get a map of bank_id => bank name.
     *
     * @return array<int, string>
     */
    public function getBankLookup(): array
    {
        if ($this->bankLookup === null) {
            $this->bankLookup = Bank::query()->pluck('name', 'id')->all();
        }

        return $this->bankLookup;
    }

    /**
     * Extract normalized trailing digits of a phone number.
     * E.g. 0842575612 or +27842575612 -> 842575612
     */
    public function extractNormalizedPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return null;
        }

        return strlen($digits) >= 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Extract a clean valid email, ignoring auto-generated placeholder emails.
     */
    public function extractValidEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $clean = mb_strtolower(trim((string) $email));
        if ($clean === '' || str_starts_with($clean, 'import_') || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $clean;
    }

    /**
     * Find other client records in different banks that represent the same person.
     *
     * Rules:
     * 1. If client has an ID Number:
     *    - Match other clients with the SAME non-empty id_number in a different bank.
     *    - OR match other clients in a different bank that have an EMPTY/BLANK id_number
     *      and match this client's phone or email.
     *    - NEVER match other clients that have a DIFFERENT non-empty id_number.
     * 2. If client has NO ID Number (empty/blank):
     *    - Fall back to matching other clients by phone or email in a different bank.
     *
     * @return Collection<int, Client>
     */
    public function findLinkedClients(Client $client): Collection
    {
        $idNumber = trim((string) ($client->id_number ?? ''));
        $normalizedPhone = $this->extractNormalizedPhone($client->phone ?: $client->cell_phone);
        $validEmail = $this->extractValidEmail($client->email);
        $currentBankId = $client->bank_id;

        $query = Client::query()
            ->where('id', '!=', $client->id)
            ->where(function ($q) use ($currentBankId) {
                if ($currentBankId) {
                    $q->where('bank_id', '!=', $currentBankId)->orWhereNull('bank_id');
                }
            });

        if ($idNumber !== '') {
            $query->where(function ($q) use ($idNumber, $normalizedPhone, $validEmail) {
                $q->where('id_number', $idNumber);

                if ($normalizedPhone !== null || $validEmail !== null) {
                    $q->orWhere(function ($sub) use ($normalizedPhone, $validEmail) {
                        $sub->where(function ($emptyId) {
                            $emptyId->whereNull('id_number')->orWhere('id_number', '');
                        });
                        $sub->where(function ($contact) use ($normalizedPhone, $validEmail) {
                            $hasClause = false;
                            if ($normalizedPhone !== null) {
                                $contact->where(function ($pQ) use ($normalizedPhone) {
                                    $pQ->where('phone', 'like', "%{$normalizedPhone}")
                                       ->orWhere('cell_phone', 'like', "%{$normalizedPhone}");
                                });
                                $hasClause = true;
                            }
                            if ($validEmail !== null) {
                                if ($hasClause) {
                                    $contact->orWhere('email', $validEmail);
                                } else {
                                    $contact->where('email', $validEmail);
                                }
                            }
                        });
                    });
                }
            });
        } else {
            if ($normalizedPhone === null && $validEmail === null) {
                return collect();
            }

            $query->where(function ($q) use ($normalizedPhone, $validEmail) {
                $hasClause = false;
                if ($normalizedPhone !== null) {
                    $q->where(function ($pQ) use ($normalizedPhone) {
                        $pQ->where('phone', 'like', "%{$normalizedPhone}")
                           ->orWhere('cell_phone', 'like', "%{$normalizedPhone}");
                    });
                    $hasClause = true;
                }
                if ($validEmail !== null) {
                    if ($hasClause) {
                        $q->orWhere('email', $validEmail);
                    } else {
                        $q->where('email', $validEmail);
                    }
                }
            });
        }

        return $query->with('bank:id,name')->get(['id', 'name', 'bank_id', 'bank_name', 'id_number', 'phone', 'cell_phone', 'email', 'tags']);
    }

    /**
     * Resolve the distinct other bank names associated with this client.
     *
     * @return array<string>
     */
    public function resolveLinkedInstitutions(Client $client): array
    {
        $currentBankName = trim((string) ($client->bank?->name ?: $client->bank_name));
        $linkedClients = $this->findLinkedClients($client);

        $bankNames = [];
        foreach ($linkedClients as $match) {
            $name = trim((string) ($match->bank?->name ?: $match->bank_name));
            if ($name !== '' && $name !== $currentBankName) {
                $bankNames[] = $name;
            }
        }

        return array_values(array_unique(array_filter($bankNames)));
    }

    /**
     * Batch resolve linked institutions for a collection of clients (e.g. on paginated index).
     * Runs in optimized queries to avoid N+1 overhead.
     *
     * @param Collection<int, Client> $clients
     * @return array<int, array<string>> Map of client_id => array of other bank names
     */
    public function resolveLinkedInstitutionsForCollection(Collection $clients): array
    {
        if ($clients->isEmpty()) {
            return [];
        }

        $idNumbers = [];
        $phones = [];
        $emails = [];

        foreach ($clients as $client) {
            $id = trim((string) ($client->id_number ?? ''));
            if ($id !== '') {
                $idNumbers[] = $id;
            }

            $phoneNorm = $this->extractNormalizedPhone($client->phone ?: $client->cell_phone);
            if ($phoneNorm !== null) {
                $phones[] = $phoneNorm;
            }

            $emailVal = $this->extractValidEmail($client->email);
            if ($emailVal !== null) {
                $emails[] = $emailVal;
            }
        }

        $idNumbers = array_values(array_unique($idNumbers));
        $phones = array_values(array_unique($phones));
        $emails = array_values(array_unique($emails));

        if (empty($idNumbers) && empty($phones) && empty($emails)) {
            $result = [];
            foreach ($clients as $c) {
                $result[$c->id] = [];
            }
            return $result;
        }

        $peerQuery = Client::query()->where(function ($q) use ($idNumbers, $phones, $emails) {
            $clauseAdded = false;
            if (!empty($idNumbers)) {
                $q->whereIn('id_number', $idNumbers);
                $clauseAdded = true;
            }
            if (!empty($phones) || !empty($emails)) {
                $closure = function ($sub) use ($phones, $emails) {
                    $hasSub = false;
                    if (!empty($phones)) {
                        $sub->where(function ($pQ) use ($phones) {
                            foreach ($phones as $p) {
                                $pQ->orWhere('phone', 'like', "%{$p}")
                                   ->orWhere('cell_phone', 'like', "%{$p}");
                            }
                        });
                        $hasSub = true;
                    }
                    if (!empty($emails)) {
                        if ($hasSub) {
                            $sub->orWhereIn('email', $emails);
                        } else {
                            $sub->whereIn('email', $emails);
                        }
                    }
                };

                if ($clauseAdded) {
                    $q->orWhere($closure);
                } else {
                    $q->where($closure);
                }
            }
        })->with('bank:id,name')->get(['id', 'name', 'bank_id', 'bank_name', 'id_number', 'phone', 'cell_phone', 'email']);

        $result = [];
        foreach ($clients as $client) {
            $cId = $client->id;
            $cBankId = $client->bank_id;
            $cBankName = trim((string) ($client->bank?->name ?: $client->bank_name));
            $cIdNumber = trim((string) ($client->id_number ?? ''));
            $cPhone = $this->extractNormalizedPhone($client->phone ?: $client->cell_phone);
            $cEmail = $this->extractValidEmail($client->email);

            $matchedBankNames = [];

            foreach ($peerQuery as $peer) {
                if ($peer->id === $cId) {
                    continue;
                }

                if ($cBankId && $peer->bank_id === $cBankId) {
                    continue;
                }

                $peerBankName = trim((string) ($peer->bank?->name ?: $peer->bank_name));
                if ($peerBankName === '' || $peerBankName === $cBankName) {
                    continue;
                }

                $peerIdNumber = trim((string) ($peer->id_number ?? ''));
                $peerPhone = $this->extractNormalizedPhone($peer->phone ?: $peer->cell_phone);
                $peerEmail = $this->extractValidEmail($peer->email);

                $isMatch = false;

                if ($cIdNumber !== '') {
                    if ($peerIdNumber === $cIdNumber) {
                        $isMatch = true;
                    } elseif ($peerIdNumber === '') {
                        if (($cPhone !== null && $peerPhone !== null && $cPhone === $peerPhone) ||
                            ($cEmail !== null && $peerEmail !== null && $cEmail === $peerEmail)) {
                            $isMatch = true;
                        }
                    }
                } else {
                    if ($cPhone !== null && $peerPhone !== null && $cPhone === $peerPhone) {
                        $isMatch = true;
                    } elseif ($cEmail !== null && $peerEmail !== null && $cEmail === $peerEmail) {
                        $isMatch = true;
                    }
                }

                if ($isMatch) {
                    $matchedBankNames[] = $peerBankName;
                }
            }

            $result[$cId] = array_values(array_unique(array_filter($matchedBankNames)));
        }

        return $result;
    }

    /**
     * Synchronize a client's tags with its linked bank names.
     * Also optionally updates the peer clients' tags to maintain bidirectional consistency.
     *
     * @return array<string> The updated tags array for the client
     */
    public function syncClientInstitutionTags(Client $client, bool $syncPeers = true): array
    {
        $allBankNames = array_values(array_unique(array_filter($this->getBankLookup())));
        $linkedInstitutions = $this->resolveLinkedInstitutions($client);

        $currentTags = is_array($client->tags) ? $client->tags : [];
        $nonBankTags = array_values(array_filter($currentTags, function ($t) use ($allBankNames) {
            return !in_array($t, $allBankNames, true);
        }));

        $updatedTags = array_values(array_unique(array_merge($nonBankTags, $linkedInstitutions)));

        // Only save if tags have changed
        if (array_values($currentTags) !== $updatedTags) {
            $client->forceFill(['tags' => $updatedTags])->save();
        }

        if ($syncPeers) {
            $peerClients = $this->findLinkedClients($client);
            foreach ($peerClients as $peer) {
                // Synchronize each peer without recursing
                $this->syncClientInstitutionTags($peer, syncPeers: false);
            }
        }

        return $updatedTags;
    }

    /**
     * Synchronize institution tags across all clients (or for a specific bank).
     */
    public function syncAllClientInstitutionTags(?int $bankId = null): int
    {
        $query = Client::query();
        if ($bankId) {
            $query->where('bank_id', $bankId);
        }

        $count = 0;
        $query->chunkById(100, function ($clients) use (&$count) {
            foreach ($clients as $client) {
                $this->syncClientInstitutionTags($client, syncPeers: false);
                $count++;
            }
        });

        return $count;
    }
}
