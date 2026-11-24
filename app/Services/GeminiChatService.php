<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Menjawab pertanyaan pengguna secara cerdas menggunakan Gemini AI.
 *
 * Mendukung riwayat percakapan (multi-turn) agar pertanyaan lanjutan
 * (misal: "nama alamatnya apa", "rutenya ke mana saja") tetap nyambung
 * dengan topik bandara sebelumnya.
 */
class GeminiChatService
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.key');
        $this->model = (string) config('services.gemini.model', 'gemini-flash-lite-latest');
        $this->baseUrl = (string) config('services.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/models/');
    }

    /**
     * Apakah Gemini API aktif dan siap digunakan.
     */
    public function tersedia(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * Kirim pertanyaan ke Gemini dengan riwayat percakapan dan konteks data.
     *
     * @param  list<array{role: string, text: string}>  $history
     * @param  array<int, array{judul: string, sub: string, url: string}>  $items
     * @return array{text: string, items: array<int, array{judul: string, sub: string, url: string}>, saran: list<string>}|null
     */
    public function answer(string $pesan, array $history = [], string $konteks = '', array $items = []): ?array
    {
        if (! $this->tersedia()) {
            return null;
        }

        $pesan = trim($pesan);
        if ($pesan === '') {
            return [
                'text' => 'Halo! Ada yang bisa aku bantu seputar bandara di Asia Tenggara?',
                'items' => [],
                'saran' => ChatService::contoh(),
            ];
        }

        $contents = $this->buildContents($history, $pesan);
        $systemPrompt = $this->buildSystemPrompt($konteks);

        $modelsToTry = array_unique([$this->model, 'gemini-flash-lite-latest', 'gemini-3.8-flash']);

        foreach ($modelsToTry as $m) {
            $result = $this->callApi($m, $systemPrompt, $contents);
            if ($result !== null) {
                return [
                    'text' => $result,
                    'items' => $items,
                    'saran' => count($history) <= 1 ? ChatService::contoh() : [],
                ];
            }
        }

        return null;
    }

    /**
     * Panggil endpoint Gemini API.
     *
     * @param  list<array{role: string, parts: list<array{text: string}>}>  $contents
     */
    private function callApi(string $model, string $systemPrompt, array $contents): ?string
    {
        $endpoint = $this->baseUrl.$model.':generateContent?key='.$this->apiKey;

        try {
            $response = Http::withOptions([
                'force_ip_resolve' => 'v4',
            ])
                ->retry(2, 1200, throw: false)
                ->timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1500,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (! empty($text)) {
                    return trim($text);
                }
            }

            Log::warning("Gemini API call failed for model {$model}", [
                'status' => $response->status(),
                'error' => $response->json()['error'] ?? substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Gemini API exception for model {$model}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Susun riwayat percakapan yang valid untuk Gemini API.
     * Aturan Gemini: peran harus bergantian (user -> model -> user).
     *
     * @param  list<array{role: string, text: string}>  $history
     * @return list<array{role: string, parts: list<array{text: string}>}>
     */
    private function buildContents(array $history, string $pesanBaru): array
    {
        $contents = [];
        $lastRole = null;

        // Ambil maksimal 8 percakapan terakhir agar tetap fokus dan hemat token
        $recent = array_slice($history, -8);

        foreach ($recent as $msg) {
            $text = trim($msg['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $rawRole = strtolower($msg['role'] ?? '');
            $role = in_array($rawRole, ['bot', 'assistant', 'model'], true) ? 'model' : 'user';

            // Cegah peran ganda berturut-turut (misal user lalu user lagi)
            if ($role === $lastRole) {
                $lastIndex = count($contents) - 1;
                $contents[$lastIndex]['parts'][0]['text'] .= "\n".$text;

                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
            $lastRole = $role;
        }

        // Pastikan diakhiri dengan pertanyaan terbaru dari user
        if ($lastRole === 'user') {
            $lastIndex = count($contents) - 1;
            $contents[$lastIndex]['parts'][0]['text'] .= "\n".$pesanBaru;
        } else {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $pesanBaru]],
            ];
        }

        return $contents;
    }

    private function buildSystemPrompt(string $konteks): string
    {
        $prompt = <<<'PROMPT'
Kamu adalah asisten kecerdasan buatan (AI) yang ramah, sopan, dan informatif untuk portal web Bandara ASEAN.
Tugas utamamu adalah membantu pengguna mencari dan memahami informasi seputar bandara, maskapai penerbangan, fasilitas, rute, dan kota di kawasan Asia Tenggara (Indonesia, Malaysia, Singapura, Thailand, Filipina, Vietnam, dsb).

Pedoman menjawab:
1. Gunakan bahasa Indonesia yang santun, jelas, dan luwes selayaknya AI modern.
2. Panjang jawaban ideal adalah 2-4 kalimat yang padat dan informatif (jangan terlalu panjang kecuali diminta detail).
3. PENTING: Pahami konteks riwayat percakapan sebelumnya! Jika pengguna bertanya "nama alamatnya apa?", "siapa pengelolanya?", atau "ada rute ke mana saja?", hubungkan secara cerdas dengan nama bandara yang sedang dibahas pada obrolan sebelumnya.
4. Jika ditanya hal umum di luar penerbangan atau bandara Asia Tenggara, jawab dengan ramah namun arahkan kembali ke topik bandara ASEAN.
PROMPT;

        if ($konteks !== '') {
            $prompt .= "\n\nBerikut referensi data bandara dari sistem untuk membantumu menjawab secara akurat:\n".$konteks;
        }

        return $prompt;
    }
}
