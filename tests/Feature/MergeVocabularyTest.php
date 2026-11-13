<?php

namespace Tests\Feature;

use Tests\TestCase;

class MergeVocabularyTest extends TestCase
{
    private string $out;

    private string $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->out = storage_path('framework/testing/merged-bandara.nq');
        $this->report = storage_path('framework/testing/merge-report.json');

        @unlink($this->out);
        @unlink($this->report);
    }

    protected function tearDown(): void
    {
        @unlink($this->out);
        @unlink($this->report);

        parent::tearDown();
    }

    public function test_vocabulary_lama_digabung_tanpa_menimpa_kanonik(): void
    {
        $this->artisan('data:merge-vocab', [
            'file' => base_path('tests/Fixtures/sample-merge.nq'),
            '--out' => $this->out,
            '--report' => $this->report,
        ])->assertSuccessful();

        $laporan = json_decode((string) file_get_contents($this->report), true);

        $this->assertSame(1, $laporan['kanonik']);
        $this->assertSame(2, $laporan['lama']);
        $this->assertSame(2, $laporan['gabungan']);
        $this->assertSame(1, $laporan['hanya_lama']);
        $this->assertSame(['KNO'], $laporan['iata_hanya_lama']);
        $this->assertCount(1, $laporan['konflik']);
        $this->assertSame('operator', $this->lokal($laporan['konflik'][0]['predikat']));
        $this->assertSame('PT Angkasa Pura II', $laporan['konflik'][0]['kanonik']);
        $this->assertSame('Operator Lama', $laporan['konflik'][0]['lama']);

        $hasil = (string) file_get_contents($this->out);

        $this->assertStringContainsString('<http://example.org/asean-airport#airport_KNO>', $hasil);
        $this->assertStringContainsString('"Kualanamu International Airport"', $hasil);
        $this->assertStringContainsString('<http://example.org/asean-airport#annualPassengers> "54000000"', $hasil);
        $this->assertStringNotContainsString('Operator Lama', $hasil);
        $this->assertStringNotContainsString('http://example.org/bandara#', $hasil);
    }

    public function test_berkas_sumber_tidak_ditemukan_memberi_pesan_jelas(): void
    {
        $this->artisan('data:merge-vocab', [
            'file' => base_path('tests/Fixtures/tidak-ada.nq'),
            '--out' => $this->out,
            '--report' => $this->report,
        ])->assertFailed();
    }

    private function lokal(string $iri): string
    {
        $posisi = strrpos($iri, '#');

        return $posisi === false ? $iri : substr($iri, $posisi + 1);
    }
}
