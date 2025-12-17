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
        Schema::create('channel_order_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();

            $table->string('type', 20); // billing|shipping

            $table->string('name', 191)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company', 191)->nullable();

            $table->string('address1', 191)->nullable();
            $table->string('address2', 191)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('zip', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('country_code', 10)->nullable();

            $table->decimal('latitude', 12, 7)->nullable();
            $table->decimal('longitude', 12, 7)->nullable();

            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['channel_order_id', 'type'], 'uq_channel_order_addresses_order_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_order_addresses');
    }
};
