<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppFlow;
use App\Models\WhatsappTemplateCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhatsAppFlowController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeView();

        $flows = WhatsAppFlow::query()
            ->latest()
            ->get()
            ->map(function (WhatsAppFlow $flow) {
                return $this->transform($flow);
            });

        return response()->json($flows);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate($this->validationRules(false));
        $template = $this->approvedTemplate($data['template_sid'], 'template_sid');
        $data['template_variables'] = $this->normalizeTemplateMappings(
            $template,
            $data['template_variables'] ?? [],
            'template_variables'
        );
        $data['flow_definition'] = $this->normalizeFlowDefinition($data['flow_definition']);

        $flow = WhatsAppFlow::create([
            ...$data,
            'status'     => $data['status'] ?? 'active',
            'created_by' => Auth::id(),
        ]);

        return response()->json($this->transform($flow), 201);
    }

    public function show(WhatsAppFlow $whatsappFlow): JsonResponse
    {
        $this->authorizeView();

        return response()->json($this->transform($whatsappFlow));
    }

    public function update(Request $request, WhatsAppFlow $whatsappFlow): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate($this->validationRules(true));

        if (array_key_exists('template_sid', $data) || array_key_exists('template_variables', $data)) {
            $templateSid = $data['template_sid'] ?? $whatsappFlow->template_sid;
            $template = $this->approvedTemplate($templateSid, 'template_sid');
            $data['template_variables'] = $this->normalizeTemplateMappings(
                $template,
                $data['template_variables'] ?? ($whatsappFlow->template_variables ?? []),
                'template_variables'
            );
        }

        if (array_key_exists('flow_definition', $data)) {
            $data['flow_definition'] = $this->normalizeFlowDefinition($data['flow_definition']);
        }

        $whatsappFlow->fill($data);
        $whatsappFlow->save();

        return response()->json($this->transform($whatsappFlow));
    }

    private function validationRules(bool $updating): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name'              => [$required, 'string', 'max:150'],
            'description'       => ['nullable', 'string'],
            'template_sid'      => [$required, 'string', 'max:255'],
            'template_name'     => ['nullable', 'string', 'max:255'],
            'template_language' => ['nullable', 'string', 'max:50'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['array'],
            'template_variables.*.source' => ['nullable', 'string', 'max:100'],
            'template_variables.*.custom_value' => ['nullable', 'string', 'max:1024'],
            'status'            => [$updating ? 'sometimes' : 'nullable', 'string', 'max:50'],
            'flow_definition'   => [$required, 'array', 'min:1'],
            'flow_definition.*' => ['array'],
            'flow_definition.*.id' => ['required', 'string', 'max:100'],
            'flow_definition.*.label' => ['nullable', 'string', 'max:150'],
            'flow_definition.*.reply_type' => ['nullable', Rule::in(['message', 'template'])],
            'flow_definition.*.message' => ['nullable', 'string'],
            'flow_definition.*.expected_replies' => ['nullable', 'array'],
            'flow_definition.*.expected_replies.*' => ['string', 'max:255'],
            'flow_definition.*.template_sid' => ['nullable', 'string', 'max:255'],
            'flow_definition.*.template_variables' => ['nullable', 'array'],
            'flow_definition.*.template_variables.*' => ['array'],
            'flow_definition.*.template_variables.*.source' => ['nullable', 'string', 'max:100'],
            'flow_definition.*.template_variables.*.custom_value' => ['nullable', 'string', 'max:1024'],
        ];
    }

    private function normalizeFlowDefinition(array $steps): array
    {
        $templateSids = collect($steps)
            ->filter(fn ($step) => is_array($step) && ($step['reply_type'] ?? 'message') === 'template')
            ->pluck('template_sid')
            ->filter()
            ->unique()
            ->values();

        $templates = WhatsappTemplateCache::query()
            ->whereIn('sid', $templateSids)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->get()
            ->keyBy('sid');

        $seenIds = [];

        return collect($steps)->values()->map(function (array $step, int $index) use ($templates, &$seenIds) {
            $field = "flow_definition.{$index}";
            $stepId = trim((string) ($step['id'] ?? ''));

            if (isset($seenIds[$stepId])) {
                throw ValidationException::withMessages([
                    "{$field}.id" => ['Every flow step must have a unique ID.'],
                ]);
            }
            $seenIds[$stepId] = true;

            $replyType = $step['reply_type'] ?? 'message';
            $step['reply_type'] = $replyType;
            $expectedReplies = collect($step['expected_replies'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->unique(fn ($value) => mb_strtolower($value))
                ->values()
                ->all();
            $step['expected_replies'] = $expectedReplies;
            unset($step['expected_replies_text']);

            if ($replyType === 'message') {
                if (trim((string) ($step['message'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        "{$field}.message" => ['Enter the message to send for this flow step.'],
                    ]);
                }

                foreach (['template_sid', 'template_name', 'template_language', 'template_preview', 'template_header_text', 'template_footer_text', 'template_variables'] as $key) {
                    unset($step[$key]);
                }

                return $step;
            }

            $templateSid = trim((string) ($step['template_sid'] ?? ''));
            $template = $templates->get($templateSid);

            if (!$template) {
                throw ValidationException::withMessages([
                    "{$field}.template_sid" => ['Select an approved WhatsApp template for this flow step.'],
                ]);
            }

            $step['message'] = '';
            $step['template_sid'] = $template->sid;
            $step['template_name'] = $template->friendly_name;
            $step['template_language'] = $template->language;
            $step['template_preview'] = $template->body_preview;
            $step['template_header_text'] = $template->header_text;
            $step['template_footer_text'] = $template->footer_text;
            $step['template_variables'] = $this->normalizeTemplateMappings(
                $template,
                $step['template_variables'] ?? [],
                "{$field}.template_variables"
            );

            return $step;
        })->all();
    }

    private function approvedTemplate(string $templateSid, string $field): WhatsappTemplateCache
    {
        $template = WhatsappTemplateCache::query()
            ->where('sid', $templateSid)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->first();

        if (!$template) {
            throw ValidationException::withMessages([
                $field => ['Select an approved WhatsApp template.'],
            ]);
        }

        return $template;
    }

    private function normalizeTemplateMappings(
        WhatsappTemplateCache $template,
        array $submittedMappings,
        string $field
    ): array {
        $normalizedMappings = [];
        $allowedSources = $this->allowedTemplateVariableSources();

        foreach (array_keys($template->variables ?? []) as $variableKey) {
            $mapping = $submittedMappings[$variableKey] ?? null;
            $source = is_array($mapping) ? trim((string) ($mapping['source'] ?? '')) : '';

            if (!in_array($source, $allowedSources, true)) {
                throw ValidationException::withMessages([
                    "{$field}.{$variableKey}.source" => ["Select a value for template variable {$variableKey}."],
                ]);
            }

            $customValue = is_array($mapping) ? trim((string) ($mapping['custom_value'] ?? '')) : '';
            if ($source === 'custom' && $customValue === '') {
                throw ValidationException::withMessages([
                    "{$field}.{$variableKey}.custom_value" => ["Enter a custom value for template variable {$variableKey}."],
                ]);
            }

            $normalizedMappings[$variableKey] = [
                'source' => $source,
                'custom_value' => $source === 'custom' ? $customValue : '',
            ];
        }

        return $normalizedMappings;
    }

    private function allowedTemplateVariableSources(): array
    {
        return [
            'client.name',
            'client.title',
            'client.first_name',
            'client.surname',
            'client.phone',
            'client.email',
            'client.id_number',
            'client.account_number',
            'client.easy_pay_number',
            'client.bank_name',
            'client.branch_code',
            'client.outstanding_balance',
            'client.arrears_amount',
            'client.settlement_amount',
            'client.three_months_amount',
            'client.installment_amount',
            'campaign.name',
            'campaign.status',
            'custom',
        ];
    }

    public function destroy(WhatsAppFlow $whatsappFlow): JsonResponse
    {
        $this->authorizeManage();

        $whatsappFlow->delete();

        return response()->json([], 204);
    }

    private function transform(WhatsAppFlow $flow): array
    {
        return [
            'id'                => $flow->id,
            'name'              => $flow->name,
            'description'       => $flow->description,
            'template_sid'      => $flow->template_sid,
            'template_name'     => $flow->template_name,
            'template_language' => $flow->template_language,
            'template_variables' => $flow->template_variables ?? [],
            'status'            => $flow->status,
            'flow_definition'   => $flow->flow_definition,
            'created_by'        => $flow->created_by,
            'created_at'        => $flow->created_at,
        ];
    }

    private function authorizeView(): void
    {
        $user = Auth::user();

        if (!$user || !$user->canManageWhatsAppFlows()) {
            abort(403, 'You are not allowed to access WhatsApp flows.');
        }
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();

        if (!$user || !$user->canManageWhatsAppFlows()) {
            abort(403, 'You are not allowed to manage WhatsApp flows.');
        }
    }
}
