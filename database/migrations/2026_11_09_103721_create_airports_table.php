<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('label')->index();
            $table->string('iata_code', 3)->nullable()->unique();
            $table->string('icao_code', 4)->nullable()->index();
            $table->string('category')->nullable();
            $table->string('status')->nullable();

            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 11, 6)->nullable();
            $table->decimal('elevation', 8, 2)->nullable();

            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->boolean('in_asean')->default(false)->index();
            $table->foreignId('nearest_airport_id')->nullable()->constrained('airports')->nullOnDelete();

            $table->decimal('distance_to_city_center_km', 8, 3)->nullable();
            $table->decimal('distance_to_nearest_airport_km', 8, 3)->nullable();
            $table->decimal('longest_runway_length', 8, 2)->nullable();
            $table->decimal('longest_runway_width', 8, 2)->nullable();
            $table->unsignedSmallInteger('runway_count')->nullable();
            $table->string('runway_designation')->nullable();
            $table->string('surface_material')->nullable();
            $table->string('operator')->nullable();

            $table->unsignedBigInteger('annual_passengers')->nullable();
            $table->unsignedBigInteger('peak_annual_passengers')->nullable();
            $table->unsignedInteger('number_of_airlines')->nullable();
            $table->unsignedInteger('number_of_destinations')->nullable();
            $table->unsignedInteger('number_of_destinations_asean')->nullable();
            $table->unsignedInteger('number_of_destinations_outside_asean')->nullable();

            $table->string('local_name', 512)->nullable();
            $table->string('website', 2048)->nullable();
            $table->unsignedSmallInteger('opened_year')->nullable();
            $table->string('dbpedia_url', 2048)->nullable();
            $table->string('wikidata_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index(['country_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
