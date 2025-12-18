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
        Schema::table('walmart_accounts', function (Blueprint $table) {
            $table->boolean('auto_sync_enabled')->default(false)->after('is_active');
            $table->unsignedSmallInteger('auto_sync_minutes')->default(15)->after('auto_sync_enabled'); // mỗi bao nhiêu phút
            $table->timestamp('last_auto_synced_at')->nullable()->after('auto_sync_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('walmart_accounts', function (Blueprint $table) {
            $table->dropColumn(['auto_sync_enabled','auto_sync_minutes','last_auto_synced_at']);
        });
    }
};
