<?php

use EchoChat\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::name('workspaces'), function (Blueprint $table) {
            $table->text('ai_prompt')->nullable()->after('allow_member_channel_deletion');
        });
    }

    public function down(): void
    {
        Schema::table(Tables::name('workspaces'), function (Blueprint $table) {
            $table->dropColumn('ai_prompt');
        });
    }
};
