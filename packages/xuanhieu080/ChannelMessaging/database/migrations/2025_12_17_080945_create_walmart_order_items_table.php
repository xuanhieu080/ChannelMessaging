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
        Schema::create('walmart_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walmart_order_id')->constrained('walmart_orders')->cascadeOnDelete();

            $table->string('line_number')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_name')->nullable();
            $table->integer('qty')->default(0);

            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_total', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->string('shipping_method')->nullable();
            $table->string('fulfillment_type')->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['walmart_order_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walmart_order_items');
    }
};
