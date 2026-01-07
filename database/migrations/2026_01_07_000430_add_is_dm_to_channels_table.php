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
        Schema::table(Tables::name('channels'), function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->boolean('is_dm')->default(false)->after('is_private');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('channels'), function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropColumn('is_dm');
        });
    }
};
