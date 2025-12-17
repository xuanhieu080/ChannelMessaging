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
        Schema::create('channel_orders', function (Blueprint $table) {
            $table->id();

            $table->string('source', 50);                    // shopify/ebay/walmart...
            $table->string('store_key', 191)->nullable();     // thien-tu-store-2.myshopify.com (nếu multi-store)

            $table->string('external_id', 191);              // shopify order id (6800...)
            $table->string('external_gid', 255)->nullable(); // admin_graphql_api_id
            $table->string('name', 50)->nullable();          // #1001
            $table->unsignedBigInteger('order_number')->nullable();
            $table->string('currency', 10)->nullable();

            $table->string('financial_status', 50)->nullable();
            $table->string('fulfillment_status', 50)->nullable();

            $table->decimal('subtotal_price', 18, 2)->nullable();
            $table->decimal('total_price', 18, 2)->nullable();
            $table->decimal('total_tax', 18, 2)->nullable();
            $table->decimal('total_discounts', 18, 2)->nullable();

            $table->string('email', 191)->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->string('customer_external_id', 191)->nullable(); // customer.id
            $table->string('customer_email', 191)->nullable();

            $table->text('note')->nullable();
            $table->string('tags', 255)->nullable();

            $table->timestampTz('created_at_shop')->nullable();
            $table->timestampTz('updated_at_shop')->nullable();
            $table->timestampTz('processed_at_shop')->nullable();

            $table->json('raw_json')->nullable();

            $table->timestamps();

            $table->unique(['source', 'store_key', 'external_id'], 'uq_channel_orders_source_store_external');
            $table->index(['source', 'store_key', 'created_at_shop']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_orders');
    }
};
