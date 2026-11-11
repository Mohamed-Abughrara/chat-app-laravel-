<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('web');
            $table->string('push_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'friend_id']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('member_count')->default(0); // app enforces <= 100
            $table->timestamps();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_low');
            $table->unsignedBigInteger('user_high');
            $table->unique(['user_low', 'user_high']);
            $table->index('user_high');
        });

        // 1-on-1 messages. message_id (Snowflake) defines order; created_at is display only.
        Schema::create('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('message_id')->primary();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('message_from');
            $table->unsignedBigInteger('message_to');
            $table->mediumText('content'); // up to 100,000 chars (MessageBody)
            $table->string('client_msg_id', 64)->nullable();
            $table->dateTime('created_at', 3);
            $table->index(['conversation_id', 'message_id']);
        });

        Schema::create('group_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('message_id')->primary();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');
            $table->mediumText('content');
            $table->string('client_msg_id', 64)->nullable();
            $table->dateTime('created_at', 3);
            $table->index(['channel_id', 'message_id']);
        });

        // Per-user inbox pointers for multi-device sync.
        Schema::create('user_inbox', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('conversation_id');
            $table->boolean('is_group')->default(false);
            $table->primary(['user_id', 'message_id']);
        });

        Schema::create('message_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('delivered_at')->useCurrent();
            $table->primary(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['message_receipts', 'user_inbox', 'group_messages', 'messages', 'conversations', 'group_members', 'groups', 'friendships', 'devices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
