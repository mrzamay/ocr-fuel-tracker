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
        Schema::create('fuel_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->nullable()->comment('Сумма (₽)');
            $table->decimal('volume', 8, 2)->nullable()->comment('Объем топлива (литры)');
            $table->date('date')->nullable();
            $table->string('receipt_image_path')->nullable();
            // Статусы: ocr_pending, success, manual
            $table->string('status')->default('manual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_records');
    }
};
