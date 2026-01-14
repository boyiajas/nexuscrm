<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwilioWhatsAppService;
use Illuminate\Http\JsonResponse;

class TwilioController extends Controller
{
    public function whatsappSenders(TwilioWhatsAppService $service): JsonResponse
    {
        $senders = $service->listWhatsappSenders();

        return response()->json($senders);
    }
}
