<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_records', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->decimal('unit_price', 8, 2)->nullable()->after('volume');
            $table->boolean('is_full_tank')->default(true)->after('unit_price');
            $table->decimal('latitude', 10, 7)->nullable()->after('status');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_accuracy')->nullable()->after('longitude');
            $table->json('ocr_meta')->nullable()->after('fuel_type');

            $table->index(['user_id', 'vehicle_id', 'date']);
            $table->index(['user_id', 'station_name']);
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $vehicleId = DB::table('vehicles')
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->value('id');

            if ($vehicleId) {
                DB::table('fuel_records')
                    ->where('user_id', $user->id)
                    ->update(['vehicle_id' => $vehicleId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fuel_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn([
                'unit_price',
                'is_full_tank',
                'latitude',
                'longitude',
                'location_accuracy',
                'ocr_meta',
            ]);
        });
    }
};
