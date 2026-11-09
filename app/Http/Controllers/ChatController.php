<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function ask(Request $request, ChatService $chat)
    {
        $pesan = mb_substr(trim((string) $request->query('q', '')), 0, 200);

        return response()->json($chat->answer($pesan));
    }
}
