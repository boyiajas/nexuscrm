<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappNumberControl;
use Illuminate\Validation\ValidationException;

class WhatsAppNumberControlService
{
    public function setPaused(
        string $phoneNumberId,
        ?string $displayPhoneNumber,
        ?string $qualityRating,
        bool $paused,
        ?User $user,
        ?string $reason = null
    ): WhatsappNumberControl {
        $control = WhatsappNumberControl::query()->firstOrNew([
            'phone_number_id' => $phoneNumberId,
        ]);

        $displayPhoneNumber = $displayPhoneNumber ?: $control->display_phone_number;
        $qualityRating = $this->normalizeQualityRating($qualityRating)
            ?: $this->resolveLiveQualityRating($phoneNumberId, $displayPhoneNumber)
            ?: $control->quality_rating;

        $control->fill([
            'display_phone_number' => $displayPhoneNumber,
            'normalized_display_phone_number' => $this->normalizeDisplayNumber($displayPhoneNumber),
            'quality_rating' => $qualityRating,
            'is_paused' => $paused,
            'pause_reason' => $paused
                ? ($reason ?: 'Paused by administration to allow the number to heal and recover its quality rating.')
                : null,
            'paused_at' => $paused ? now() : null,
            'paused_by_user_id' => $paused ? $user?->id : null,
        ]);
        $control->save();

        return $control->fresh('pausedBy');
    }

    public function statusFor(
        ?string $phoneNumberId,
        ?string $displayPhoneNumber = null,
        ?string $currentQualityRating = null
    ): array {
        $control = $this->findControl($phoneNumberId, $displayPhoneNumber);

        $normalizedRating = $this->normalizeQualityRating($currentQualityRating);

        // If no rating passed, attempt to resolve live/cached rating from MetaWhatsAppService
        if (! $normalizedRating) {
            $normalizedRating = $this->resolveLiveQualityRating($phoneNumberId, $displayPhoneNumber);
        }

        // If we have a fresh rating and a control record exists, keep the DB record in sync
        if ($control && $normalizedRating && $control->quality_rating !== $normalizedRating) {
            $control->update([
                'quality_rating' => $normalizedRating,
            ]);
        }

        $effectiveRating = $normalizedRating
            ?: $control?->quality_rating
            ?: 'UNKNOWN';

        return [
            'is_paused' => (bool) ($control?->is_paused ?? false),
            'phone_number_id' => $control?->phone_number_id ?: $phoneNumberId,
            'display_phone_number' => $displayPhoneNumber ?: $control?->display_phone_number,
            'quality_rating' => $effectiveRating,
            'pause_reason' => $control?->pause_reason,
            'paused_at' => $control?->paused_at?->toDateTimeString(),
            'paused_by' => $control?->pausedBy?->name,
        ];
    }

    public function resolveLiveQualityRating(?string $phoneNumberId, ?string $displayPhoneNumber = null): ?string
    {
        try {
            /** @var \App\Services\MetaWhatsAppService $metaService */
            $metaService = $phoneNumberId
                ? \App\Services\MetaWhatsAppService::forPhoneNumberId((string) $phoneNumberId)
                : app(\App\Services\MetaWhatsAppService::class);

            $senders = $metaService->listWhatsappSenders();
            $normDisplay = $this->normalizeDisplayNumber($displayPhoneNumber);

            foreach ($senders as $s) {
                $pId = (string) ($s['phone_number_id'] ?? '');
                $sNum = $this->normalizeDisplayNumber($s['number'] ?? '');

                $matchesId = $phoneNumberId && $pId && $pId === (string) $phoneNumberId;
                $matchesNum = $normDisplay && $sNum && (
                    $sNum === $normDisplay ||
                    (strlen($sNum) >= 9 && strlen($normDisplay) >= 9 && substr($sNum, -9) === substr($normDisplay, -9))
                );

                if ($matchesId || $matchesNum) {
                    if (!empty($s['quality_rating'])) {
                        return $this->normalizeQualityRating($s['quality_rating']);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fall back
        }

        return null;
    }

    public function assertCanSend(
        ?string $phoneNumberId,
        ?string $displayPhoneNumber = null,
        ?string $currentQualityRating = null
    ): void {
        $status = $this->statusFor($phoneNumberId, $displayPhoneNumber, $currentQualityRating);
        if (! $status['is_paused']) {
            return;
        }

        throw ValidationException::withMessages([
            'whatsapp_sender' => [$this->blockedMessage($status)],
        ]);
    }

    public function blockedMessage(array $status): string
    {
        $number = $status['display_phone_number'] ?: $status['phone_number_id'] ?: 'the selected number';
        $qualityRating = $status['quality_rating'] ?: 'UNKNOWN';

        return "WhatsApp sending is paused by administration for {$number} to allow the number to heal and recover its quality rating. Current quality rating: {$qualityRating}. You can save the batch as a draft, but it cannot be sent until the number is resumed.";
    }

    protected function findControl(?string $phoneNumberId, ?string $displayPhoneNumber): ?WhatsappNumberControl
    {
        if ($phoneNumberId) {
            $control = WhatsappNumberControl::query()
                ->with('pausedBy')
                ->where('phone_number_id', $phoneNumberId)
                ->first();

            if ($control) {
                return $control;
            }
        }

        $normalizedDisplayNumber = $this->normalizeDisplayNumber($displayPhoneNumber);
        if (! $normalizedDisplayNumber) {
            return null;
        }

        return WhatsappNumberControl::query()
            ->with('pausedBy')
            ->where('normalized_display_phone_number', $normalizedDisplayNumber)
            ->first();
    }

    protected function normalizeDisplayNumber(?string $number): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeQualityRating(?string $rating): ?string
    {
        $normalized = strtoupper(trim((string) $rating));

        return $normalized !== '' ? $normalized : null;
    }
}
