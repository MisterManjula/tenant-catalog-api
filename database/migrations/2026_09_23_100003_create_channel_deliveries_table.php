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
        Schema::create('channel_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained();
            $table->foreignId('channel_id')->constrained();
            $table->string('status');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // At-least-once fan-out: a listener that runs twice must not create a second delivery.
            $table->unique(['publication_id', 'channel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_deliveries');
    }
};
