<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MembuatDataBandara;
use Tests\TestCase;

class RelationalChatTest extends TestCase
{
    use MembuatDataBandara;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buatDuniaBandara();
    }

    public function test_chat_menjawab_jumlah_bandara_dari_database_relasional(): void
    {
        $this->getJson('/chat?q='.urlencode('Berapa jumlah bandara di Indonesia?'))
            ->assertOk()
            ->assertJsonPath('text', 'Ada 24 bandara di Indonesia menurut data yang tersimpan.');
    }

    public function test_chat_menampilkan_info_bandara_dari_kode_iata(): void
    {
        $this->getJson('/chat?q='.urlencode('Info CGK'))
            ->assertOk()
            ->assertJsonPath('items.0.judul', 'Buka halaman Soekarno-Hatta International Airport')
            ->assertJsonPath('items.0.url', route('bandara.show', 'CGK'));
    }

    public function test_chat_menampilkan_rute_keberangkatan(): void
    {
        $response = $this->getJson('/chat?q='.urlencode('Rute dari CGK'))
            ->assertOk()
            ->assertJsonPath('items.0.judul', 'I Gusti Ngurah Rai International Airport (DPS)')
            ->assertJsonPath('items.0.sub', '2 maskapai');

        $this->assertStringContainsString('Rute dari Soekarno-Hatta International Airport', $response->json('text'));
    }

    public function test_chat_menampilkan_maskapai_dari_database_relasional(): void
    {
        $text = $this->getJson('/chat?q='.urlencode('maskapai di CGK'))
            ->assertOk()
            ->json('text');

        $this->assertStringContainsString('AirAsia', $text);
        $this->assertStringContainsString('Garuda Indonesia', $text);
        $this->assertStringNotContainsString('Singapore Airlines', $text);
    }

    public function test_chat_tetap_berfungsi_tanpa_memanggil_fuseki(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/chat?q='.urlencode('Info Kualanamu'))
            ->assertOk()
            ->assertJsonPath('items.0.judul', 'Buka halaman Kualanamu International Airport')
            ->assertJsonPath('items.0.url', route('bandara.show', 'KNO'));
    }

    public function test_chat_menemukan_bandara_lewat_nama_kota(): void
    {
        $response = $this->getJson('/chat?q='.urlencode('Info Medan'))
            ->assertOk()
            ->assertJsonPath('items.0.judul', 'Buka halaman Kualanamu International Airport')
            ->assertJsonPath('items.0.url', route('bandara.show', 'KNO'));

        $this->assertStringContainsString('Kualanamu International Airport (KNO)', $response->json('text'));
    }

    public function test_chat_memberi_petunjuk_saat_bandara_tidak_ditemukan(): void
    {
        $this->getJson('/chat?q='.urlencode('Info bandara antah berantah'))
            ->assertOk()
            ->assertJsonPath('text', 'Aku belum menemukan bandara untuk "antah berantah". Coba nama lain, kode IATA, atau nama kotanya.');
    }
}
