<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Services\RelationalChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function ask(Request $request, ChatService $chat, RelationalChatService $relational)
    {
        $pesan = mb_substr(trim((string) $request->query('q', '')), 0, 200);

        if ($relational->tersedia()) {
            return response()->json($relational->answer($pesan));
        }

        return response()->json($chat->answer($pesan));
    }
}
