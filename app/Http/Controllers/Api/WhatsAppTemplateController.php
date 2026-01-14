<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwilioWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppTemplateController extends Controller
{
    public function __construct(private TwilioWhatsAppService $twilioWhatsApp)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $onlyApproved = filter_var($request->query('approved', '1'), FILTER_VALIDATE_BOOLEAN);
        $templates    = $this->twilioWhatsApp->getWhatsAppTemplates($onlyApproved);

        $data = array_map(function (array $t) {
            $whatsapp = $t['whatsapp'] ?? [];
            return [
                'id'           => $t['sid'],
                'sid'          => $t['sid'],
                'name'         => $t['friendly_name'] ?? $t['sid'],
                'language'     => $t['language'] ?? null,
                'category'     => $whatsapp['category'] ?? null,
                'status'       => $whatsapp['status'] ?? null,
                'body_preview' => $t['preview'] ?? null,
                'variables'    => $t['variables'] ?? [],
                'whatsapp'     => $whatsapp,
                'media_urls'   => $t['media'] ?? [],
            ];
        }, $templates);

        return response()->json($data);
    }

    public function show(string $id): JsonResponse
    {
        $details   = $this->twilioWhatsApp->getTemplateDetails($id);
        $approvals = $this->twilioWhatsApp->getTemplateApprovalStatus($id);

        return response()->json([
            'template'  => $details,
            'approvals' => $approvals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'friendly_name' => ['required', 'string', 'max:255'],
            'body'          => ['required', 'string'],
            'language'      => ['required', 'string', 'max:10'],
            'category'      => ['required', 'string', 'max:50'],
            'media_urls'    => ['array'],
            'media_urls.*'  => ['string'],
        ]);

        $created = $this->twilioWhatsApp->createWhatsAppTemplate(
            $data['friendly_name'],
            $data['body'],
            $data['language'],
            $data['category'],
            $data['media_urls'] ?? []
        );

        return response()->json($created, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'friendly_name' => ['sometimes', 'string', 'max:255'],
            'body'          => ['sometimes', 'string'],
            'language'      => ['sometimes', 'string', 'max:10'],
            'category'      => ['sometimes', 'string', 'max:50'],
            'media_urls'    => ['array'],
            'media_urls.*'  => ['string'],
        ]);

        $payload = [
            'friendly_name' => $data['friendly_name'] ?? null,
            'language'      => $data['language'] ?? null,
            'body'          => $data['body'] ?? null,
            'category'      => $data['category'] ?? null,
            'media'         => $data['media_urls'] ?? null,
        ];

        $updated = $this->twilioWhatsApp->updateWhatsAppTemplate($id, $payload);

        return response()->json($updated);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $this->twilioWhatsApp->deleteWhatsAppTemplate($id);

        return response()->json([], 204);
    }

    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->twilioWhatsApp->submitTemplateForApproval($id, $data['category']);

        return response()->json($result);
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            abort(403, 'Only SUPER_ADMIN can manage WhatsApp templates.');
        }
    }
}
