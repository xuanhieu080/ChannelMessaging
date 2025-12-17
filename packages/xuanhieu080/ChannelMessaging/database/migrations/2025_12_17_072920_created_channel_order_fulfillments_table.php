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
        Schema::create('channel_order_fulfillments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();

            $table->string('external_id', 191); // fulfillment.id
            $table->string('external_gid', 255)->nullable();

            $table->string('name', 100)->nullable();   // #1001.1
            $table->string('status', 50)->nullable();  // success
            $table->string('service', 100)->nullable();
            $table->string('shipment_status', 50)->nullable();
            $table->string('tracking_company', 191)->nullable();
            $table->string('tracking_number', 191)->nullable();
            $table->string('tracking_url', 1024)->nullable();

            $table->timestampTz('created_at_shop')->nullable();
            $table->timestampTz('updated_at_shop')->nullable();

            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['channel_order_id', 'external_id'], 'uq_channel_order_fulfillments_order_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_order_fulfillments');
    }
};
