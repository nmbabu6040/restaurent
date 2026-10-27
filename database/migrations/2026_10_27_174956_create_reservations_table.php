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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 20);
            $table->dateTime('reserved_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedTinyInteger('party_size');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->string('locale', 5)->default('en');
            $table->timestamps();

            $table->index(['reserved_at', 'status']);
            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
