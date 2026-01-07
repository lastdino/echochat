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
        Schema::table(Tables::name('messages'), function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained(Tables::name('messages'))->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('messages'), function (Blueprint $table) {
            $table->dropForeign([Tables::name('messages').'_parent_id_foreign']);
            $table->dropColumn('parent_id');
        });
    }
};
