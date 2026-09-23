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
        // Publications and their items are an immutable snapshot: no updated_at.
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // SKUs left out of the snapshot because their price was not resolved.
            $table->jsonb('withheld')->default('[]');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('publication_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained();
            $table->string('sku');
            $table->string('name');
            $table->integer('price_cents');

            $table->unique(['publication_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_items');
        Schema::dropIfExists('publications');
    }
};
