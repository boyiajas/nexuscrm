<?php

namespace App\Contracts;

interface WhatsAppServiceInterface
{
    public function listWhatsappSenders(): array;

    public function sendTemplateFromSubjectMessage(
        string $toE164,
        ?string $overrideTemplateSid,
        string $subject = '',
        string $message = '',
        ?string $overrideFrom = null,
        ?string $overrideMsid = null
    ): array;

    public function sendPlainWhatsapp(string $toE164, string $body, ?string $overrideFrom = null, ?string $overrideMsid = null): array;

    public function getWhatsAppTemplates(bool $onlyApproved = true, int $pageSize = 50): array;

    public function getTemplateDetails(string $templateId): array;

    public function getTemplateApprovalStatus(string $templateId): array;

    public function createWhatsAppTemplate(string $friendlyName, string $body, string $language = 'en_US', string $category = 'UTILITY', array $mediaUrls = []): array;

    public function updateWhatsAppTemplate(string $templateId, array $data): array;

    public function deleteWhatsAppTemplate(string $templateId): bool;

    public function submitTemplateForApproval(string $templateId, string $category = 'UTILITY'): array;
}
