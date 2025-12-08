<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channel_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source', 50);              // 'shopify', 'ebay', 'facebook', 'walmart'
            $table->string('external_id', 255);        // id tin nhắn bên hệ thống gốc
            $table->string('thread_id', 255)->nullable();
            $table->string('sender')->nullable();
            $table->string('receiver')->nullable();
            $table->enum('direction', ['in', 'out'])->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->longText('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_messages');
    }
};
