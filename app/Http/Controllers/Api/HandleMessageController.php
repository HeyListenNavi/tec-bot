<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendToN8nJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HandleMessageController extends Controller
{
    public function handle(Request $request)
    {
        $body = $request->all();

        // 1. Soporte para Evolution API webhook (event: messages.upsert)
        if (isset($body['data']['key'])) {
            $key = $body['data']['key'];

            // Ignorar mensajes enviados por el propio bot o grupos
            if (!empty($key['fromMe']) || str_ends_with((string) ($key['remoteJid'] ?? ''), '@g.us')) {
                return response()->json(['ignored' => true]);
            }

            $messageId = $key['id'] ?? null;
            if (!$messageId) {
                return response()->json(['ignored' => true]);
            }

            if (Cache::has("whatsapp_message_$messageId")) {
                return response()->json(['duplicate' => true]);
            }

            Cache::put("whatsapp_message_$messageId", true, now()->addHours(24));

            SendToN8nJob::dispatch($body);

            return response()->json(['queued' => true]);
        }

        // 2. Soporte compatible con Meta Cloud API webhook
        if (!isset($body['entry'][0]['changes'][0]['value'])) {
            return response()->json(['ignored' => true]);
        }

        $value = $body['entry'][0]['changes'][0]['value'];

        if (isset($value['statuses'])) {
            return response()->json(['ignored' => true]);
        }

        if (!isset($value['messages'][0])) {
            return response()->json(['ignored' => true]);
        }

        $message = $value['messages'][0];
        $type = $message['type'] ?? null;

        if ($type === 'reaction') {
            return response()->json(['ignored' => true]);
        }

        $allowedTypes = ['text', 'audio', 'image', 'video', 'document'];

        if (!in_array($type, $allowedTypes)) {
            return response()->json(['ignored' => true]);
        }

        $messageId = $message['id'] ?? null;

        if (!$messageId) {
            return response()->json(['ignored' => true]);
        }

        if (Cache::has("whatsapp_message_$messageId")) {
            return response()->json(['duplicate' => true]);
        }

        Cache::put("whatsapp_message_$messageId", true, now()->addHours(24));

        SendToN8nJob::dispatch($body);

        return response()->json(['queued' => true]);
    }
}
