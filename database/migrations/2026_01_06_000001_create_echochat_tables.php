<?php

use EchoChat\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::name('workspaces'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create(Tables::name('channels'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained(Tables::name('workspaces'))->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create(Tables::name('messages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained(Tables::name('channels'))->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('messages'));
        Schema::dropIfExists(Tables::name('channels'));
        Schema::dropIfExists(Tables::name('workspaces'));
    }
};
