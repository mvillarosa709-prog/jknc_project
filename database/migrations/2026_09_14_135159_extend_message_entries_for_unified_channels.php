<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_entries', function (Blueprint $table) {
            $table->string('channel', 40)
                ->nullable()
                ->after('message_conversation_id');

            $table->string('message_type', 40)
                ->default('text')
                ->after('body');

            $table->timestamp('delivered_at')
                ->nullable()
                ->after('received_at');

            $table->timestamp('read_at')
                ->nullable()
                ->after('delivered_at');

            $table->index('channel');
            $table->index('message_type');
            $table->index('delivered_at');
            $table->index('read_at');
        });

        /*
         * Existing messages inherit their conversation channel.
         * This keeps historical records consistent before the
         * channel field is enforced for future provider messages.
         */
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                'UPDATE message_entries
                 SET channel = (
                     SELECT mc.channel
                     FROM message_conversations mc
                     WHERE mc.id = message_entries.message_conversation_id
                 )
                 WHERE channel IS NULL'
            );
        } else {
            DB::statement(
                'UPDATE message_entries me
                 INNER JOIN message_conversations mc
                    ON mc.id = me.message_conversation_id
                 SET me.channel = mc.channel
                 WHERE me.channel IS NULL'
            );
        }

        /*
         * external_message_id may overlap between different providers,
         * so uniqueness is scoped by channel.
         *
         * Multiple NULL values remain permitted.
         */
        Schema::table('message_entries', function (Blueprint $table) {
            $table->unique(
                ['channel', 'external_message_id'],
                'message_entries_channel_external_message_unique'
            );
        });

        /*
         * This helps future webhook processing locate an external
         * conversation efficiently without changing current behavior.
         */
        Schema::table('message_conversations', function (Blueprint $table) {
            $table->index(
                ['channel', 'external_conversation_id'],
                'message_conversations_channel_external_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('message_conversations', function (Blueprint $table) {
            $table->dropIndex(
                'message_conversations_channel_external_index'
            );
        });

        Schema::table('message_entries', function (Blueprint $table) {
            $table->dropUnique(
                'message_entries_channel_external_message_unique'
            );

            $table->dropIndex(['channel']);
            $table->dropIndex(['message_type']);
            $table->dropIndex(['delivered_at']);
            $table->dropIndex(['read_at']);

            $table->dropColumn([
                'channel',
                'message_type',
                'delivered_at',
                'read_at',
            ]);
        });
    }
};