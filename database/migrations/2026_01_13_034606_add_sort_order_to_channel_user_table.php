<?php

use EchoChat\Support\Tables;
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
        Schema::table(Tables::name('channel_user'), function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('last_read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('channel_user'), function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
