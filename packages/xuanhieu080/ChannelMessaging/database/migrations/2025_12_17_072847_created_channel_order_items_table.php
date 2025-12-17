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
        Schema::create('channel_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();

            $table->string('external_id', 191); // line_item.id
            $table->string('external_gid', 255)->nullable();

            $table->string('title', 255)->nullable();
            $table->string('variant_title', 255)->nullable();
            $table->string('sku', 191)->nullable();

            $table->string('product_external_id', 191)->nullable();
            $table->string('variant_external_id', 191)->nullable();

            $table->integer('quantity')->nullable();
            $table->decimal('price', 18, 2)->nullable();
            $table->decimal('total_discount', 18, 2)->nullable();

            $table->string('vendor', 191)->nullable();
            $table->string('fulfillment_service', 191)->nullable();
            $table->string('fulfillment_status', 50)->nullable();

            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['channel_order_id', 'external_id'], 'uq_channel_order_items_order_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_order_items');
    }
};
