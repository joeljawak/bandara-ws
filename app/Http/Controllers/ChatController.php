<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Services\GeminiChatService;
use App\Services\RelationalChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function ask(
        Request $request,
        ChatService $chat,
        RelationalChatService $relational,
        GeminiChatService $gemini,
    ) {
        if ($request->isMethod('post')) {
            $q = $request->input('q', '');
            /** @var list<array{role: string, text: string}> $history */
            $history = (array) $request->input('history', []);
        } else {
            $q = $request->query('q', '');
            $history = [];
        }

        $pesan = is_string($q) ? mb_substr(trim($q), 0, 200) : '';
        if ($pesan === '') {
            return response()->json([
                'text' => 'Halo! Ada yang bisa aku bantu seputar bandara di Asia Tenggara?',
                'items' => [],
                'saran' => ChatService::contoh(),
            ]);
        }

        // Ambil referensi data lokal dari database relasional atau SPARQL
        $localResult = null;
        if ($relational->tersedia()) {
            try {
                $localResult = $relational->answer($pesan);
            } catch (\Throwable) {
                // Lanjut ke fallback
            }
        }

        if ($localResult === null) {
            try {
                $localResult = $chat->answer($pesan);
            } catch (\Throwable) {
                // Lanjut ke fallback
            }
        }

        // Jika Gemini aktif, gunakan Gemini sebagai asisten AI cerdas
        if ($gemini->tersedia()) {
            $konteks = '';
            $items = [];

            if ($localResult !== null) {
                $isSearchError = str_contains($localResult['text'], 'belum menemukan')
                    || str_contains($localResult['text'], 'Bandara mana yang ingin')
                    || str_contains($localResult['text'], 'Coba nama lain');

                // Jika pertanyaan pertama atau data lokal spesifik & bukan error search, masukkan sebagai konteks
                if (! $isSearchError && count($history) === 0) {
                    $konteks = $localResult['text'];
                    $items = $localResult['items'] ?? [];
                }
            }

            $aiResponse = $gemini->answer($pesan, history: $history, konteks: $konteks, items: $items);

            if ($aiResponse !== null) {
                return response()->json($aiResponse);
            }
        }

        // Fallback jika Gemini API tidak tersedia / mengalami kendala jaringan
        if ($localResult !== null) {
            return response()->json($localResult);
        }

        return response()->json([
            'text' => 'Maaf, informasi belum bisa dimuat saat ini. Coba lagi sebentar ya.',
            'items' => [],
            'saran' => ChatService::contoh(),
        ]);
    }
}
