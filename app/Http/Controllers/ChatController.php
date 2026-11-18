<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Services\RelationalChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function ask(Request $request, ChatService $chat, RelationalChatService $relational)
    {
        // ?q[]=x bukan teks; jangan sampai jadi error 500
        $q = $request->query('q', '');
        $pesan = is_string($q) ? mb_substr(trim($q), 0, 200) : '';

        if ($relational->tersedia()) {
            return response()->json($relational->answer($pesan));
        }

        return response()->json($chat->answer($pesan));
    }
}
