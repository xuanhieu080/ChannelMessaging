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
        Schema::create('walmart_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Bạn có thể lưu theo kiểu client id/secret (OAuth token) hoặc consumer/private key tùy chương trình access.
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();

            // Optional: nếu Walmart cấp theo kiểu Consumer ID / Private Key (một số flow legacy)
            $table->string('consumer_id')->nullable();
            $table->text('private_key_pem')->nullable();

            $table->string('market', 10)->default('US'); // US/CA/...
            $table->boolean('is_active')->default(true);

            // token cache (optional)
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'market']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walmart_accounts');
    }
};
