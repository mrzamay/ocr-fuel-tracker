<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('plate_number', 32)->nullable();
            $table->string('fuel_type', 100)->nullable();
            $table->unsignedInteger('current_odometer_km')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            DB::table('vehicles')->insert([
                'user_id' => $user->id,
                'name' => 'Моё авто',
                'plate_number' => null,
                'fuel_type' => 'АИ-95',
                'current_odometer_km' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
