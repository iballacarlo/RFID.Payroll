<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_attendance_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('identifier', 100);
            $table->enum('method', ['rfid', 'fingerprint']);
            $table->dateTime('occurred_at');
            $table->json('response_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_attendance_events');
    }
};
