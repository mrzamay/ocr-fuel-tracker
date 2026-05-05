<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_records', function (Blueprint $table) {
            $table->string('station_name')->nullable()->comment('Название АЗС')->after('volume');
            $table->string('fuel_type')->nullable()->comment('Вид бензина')->after('station_name');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_records', function (Blueprint $table) {
            $table->dropColumn(['station_name', 'fuel_type']);
        });
    }
};
