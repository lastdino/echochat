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
            $table->boolean('allow_member_channel_creation')->default(true);
            $table->boolean('allow_member_channel_deletion')->default(false);
            $table->timestamps();
        });

        Schema::create(Tables::name('channels'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained(Tables::name('workspaces'))->onDelete('cascade');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_dm')->default(false);
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create(Tables::name('workspace_members'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained(Tables::name('workspaces'))->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create(Tables::name('channel_members'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained(Tables::name('channels'))->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
        });

        Schema::create(Tables::name('channel_user'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained(Tables::name('channels'))->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
        });

        Schema::create(Tables::name('messages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained(Tables::name('channels'))->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained(Tables::name('messages'))->onDelete('cascade');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create(Tables::name('message_reactions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained(Tables::name('messages'))->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('emoji');
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('message_reactions'));
        Schema::dropIfExists(Tables::name('messages'));
        Schema::dropIfExists(Tables::name('channel_user'));
        Schema::dropIfExists(Tables::name('channel_members'));
        Schema::dropIfExists(Tables::name('workspace_members'));
        Schema::dropIfExists(Tables::name('channels'));
        Schema::dropIfExists(Tables::name('workspaces'));
    }
};
