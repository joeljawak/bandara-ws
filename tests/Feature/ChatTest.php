<?php

namespace Tests\Feature;

use App\Services\ChatService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatTest extends TestCase
{
    public function test_chat_box_ada_di_beranda(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="chat"', false)
            ->assertSee('Tanya Bandara ASEAN');
    }

    public function test_pertanyaan_jumlah_bandara_dijawab_dari_data(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            config('sparql.endpoint') => Http::response(['results' => ['bindings' => [
                ['n' => ['value' => '136']],
            ]]]),
        ]);

        $this->getJson('/chat?q=' . urlencode('Berapa jumlah bandara di Indonesia?'))
            ->assertOk()
            ->assertJsonPath('text', 'Ada 136 bandara di Indonesia menurut data yang tersimpan.');
    }

    public function test_jawaban_ramah_saat_fuseki_mati(): void
    {
        Http::fake([config('sparql.endpoint') => Http::response('error', 500)]);

        $this->getJson('/chat?q=' . urlencode('Berapa jumlah bandara di Indonesia?'))
            ->assertOk()
            ->assertJsonPath('text', 'Data bandara belum bisa diambil. Pastikan Fuseki sudah berjalan.');
    }

    public function test_sapaan_memberi_contoh_pertanyaan(): void
    {
        $this->getJson('/chat?q=halo')
            ->assertOk()
            ->assertJsonCount(count(ChatService::contoh()), 'saran');
    }

    /**
     * @dataProvider pertanyaan
     */
    public function test_maksud_pertanyaan_dikenali(string $pesan, string $intent): void
    {
        $this->assertSame($intent, ChatService::parse($pesan)['intent']);
    }

    public static function pertanyaan(): array
    {
        return [
            ['Berapa jumlah bandara di Indonesia?', 'hitung'],
            ['Bandara paling ramai di Thailand', 'ramai'],
            ['Landasan terpanjang di Malaysia', 'landasan'],
            ['daftar bandara di Vietnam', 'daftar'],
            ['Info Kualanamu', 'info'],
            ['Rute dari KNO', 'rute'],
            ['rute dari KNO ke CGK', 'rute_ab'],
            ['maskapai di CGK', 'maskapai'],
            ['halo', 'sapaan'],
        ];
    }

    public function test_nama_bandara_dan_negara_diambil_dari_kalimat(): void
    {
        $p = ChatService::parse('rute dari kualanamu ke changi');
        $this->assertSame('kualanamu', $p['dari']);
        $this->assertSame('changi', $p['ke']);

        $q = ChatService::parse('Bandara paling ramai di Thailand');
        $this->assertSame(['thailand'], $q['negara']);
    }
}
