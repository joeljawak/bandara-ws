<?php

namespace Tests\Feature;

use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MembuatDataBandara;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use MembuatDataBandara;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buatDuniaBandara();
    }

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

        $this->getJson('/chat?q='.urlencode('Berapa jumlah bandara di Indonesia?'))
            ->assertOk()
            ->assertJsonPath('text', 'Ada 24 bandara di Indonesia menurut data yang tersimpan.');
    }

    public function test_jawaban_tetap_ada_saat_layanan_luar_mati(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('layanan mati', 500)]);

        $this->getJson('/chat?q='.urlencode('Berapa jumlah bandara di Indonesia?'))
            ->assertOk()
            ->assertJsonPath('text', 'Ada 24 bandara di Indonesia menurut data yang tersimpan.');
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
    #[DataProvider('pertanyaan')]
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
