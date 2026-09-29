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
        $qualityRating = $this->normalizeQualityRating($qualityRating) ?: $control->quality_rating;

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

        return [
            'is_paused' => (bool) ($control?->is_paused ?? false),
            'phone_number_id' => $control?->phone_number_id ?: $phoneNumberId,
            'display_phone_number' => $displayPhoneNumber ?: $control?->display_phone_number,
            'quality_rating' => $this->normalizeQualityRating($currentQualityRating)
                ?: $control?->quality_rating
                ?: 'UNKNOWN',
            'pause_reason' => $control?->pause_reason,
            'paused_at' => $control?->paused_at?->toDateTimeString(),
            'paused_by' => $control?->pausedBy?->name,
        ];
    }

    public function assertCanSend(?string $phoneNumberId, ?string $displayPhoneNumber = null): void
    {
        $status = $this->statusFor($phoneNumberId, $displayPhoneNumber);
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
