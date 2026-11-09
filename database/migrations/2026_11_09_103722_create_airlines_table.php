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
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('label')->index();
            $table->string('airline_code', 8)->nullable()->index();
            $table->foreignId('hub_airport_id')->nullable()->constrained('airports')->nullOnDelete();
            $table->string('website', 2048)->nullable();
            $table->string('dbpedia_url', 2048)->nullable();
            $table->string('wikidata_url', 2048)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
