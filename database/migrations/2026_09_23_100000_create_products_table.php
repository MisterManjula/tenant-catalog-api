<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('sku');
            $table->string('name');
            // NULL means the price was not resolved upstream: the product is withheld on publish.
            $table->integer('price_cents')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
        });

        // A price is either resolved and positive, or explicitly absent. Never zero.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_cents_check CHECK (price_cents IS NULL OR price_cents > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
