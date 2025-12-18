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
        Schema::create('walmart_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walmart_account_id')->constrained()->cascadeOnDelete();

            $table->string('purchase_order_id')->unique(); // PO ID từ Walmart
            $table->string('customer_order_id')->nullable();
            $table->string('status')->nullable();

            $table->timestamp('order_date')->nullable();
            $table->timestamp('ship_by_date')->nullable();
            $table->timestamp('deliver_by_date')->nullable();

            $table->string('buyer_email')->nullable();
            $table->string('buyer_name')->nullable();

            $table->decimal('order_total', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->json('raw')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['walmart_account_id', 'status']);
            $table->index(['walmart_account_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walmart_orders');
    }
};
