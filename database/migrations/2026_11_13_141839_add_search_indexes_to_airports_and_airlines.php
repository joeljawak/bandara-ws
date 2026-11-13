<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, string|list<string>>>
     */
    private array $index = [
        'airports' => [
            'airports_annual_passengers_index' => 'annual_passengers',
            'airports_number_of_destinations_index' => 'number_of_destinations',
            'airports_in_asean_label_index' => ['in_asean', 'label'],
        ],
        'airlines' => [
            'airlines_label_index' => 'label',
            'airlines_airline_code_index' => 'airline_code',
        ],
        'routes' => [
            'routes_airline_id_index' => 'airline_id',
            'routes_destination_airport_id_index' => 'destination_airport_id',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->index as $tabel => $daftar) {
            foreach ($daftar as $nama => $kolom) {
                if (Schema::hasIndex($tabel, $nama)) {
                    continue;
                }

                Schema::table($tabel, function (Blueprint $table) use ($nama, $kolom): void {
                    $table->index($kolom, $nama);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->index as $tabel => $daftar) {
            foreach (array_keys($daftar) as $nama) {
                if (! Schema::hasIndex($tabel, $nama)) {
                    continue;
                }

                Schema::table($tabel, function (Blueprint $table) use ($nama): void {
                    $table->dropIndex($nama);
                });
            }
        }
    }
};
