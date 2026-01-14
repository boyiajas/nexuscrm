<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppFlowController extends Controller
{
    public function index(): JsonResponse
    {
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
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:150'],
            'description'       => ['nullable', 'string'],
            'template_sid'      => ['required', 'string', 'max:255'],
            'template_name'     => ['nullable', 'string', 'max:255'],
            'template_language' => ['nullable', 'string', 'max:50'],
            'status'            => ['nullable', 'string', 'max:50'],
            'flow_definition'   => ['required', 'array'],
        ]);

        $flow = WhatsAppFlow::create([
            ...$data,
            'status'     => $data['status'] ?? 'active',
            'created_by' => Auth::id(),
        ]);

        return response()->json($this->transform($flow), 201);
    }

    public function show(WhatsAppFlow $whatsappFlow): JsonResponse
    {
        return response()->json($this->transform($whatsappFlow));
    }

    public function update(Request $request, WhatsAppFlow $whatsappFlow): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:150'],
            'description'       => ['nullable', 'string'],
            'template_sid'      => ['sometimes', 'string', 'max:255'],
            'template_name'     => ['nullable', 'string', 'max:255'],
            'template_language' => ['nullable', 'string', 'max:50'],
            'status'            => ['sometimes', 'string', 'max:50'],
            'flow_definition'   => ['sometimes', 'array'],
        ]);

        $whatsappFlow->fill($data);
        $whatsappFlow->save();

        return response()->json($this->transform($whatsappFlow));
    }

    public function destroy(WhatsAppFlow $whatsappFlow): JsonResponse
    {
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
            'status'            => $flow->status,
            'flow_definition'   => $flow->flow_definition,
            'created_by'        => $flow->created_by,
            'created_at'        => $flow->created_at,
        ];
    }
}
