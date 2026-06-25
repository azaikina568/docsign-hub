<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbox для consumer'ов: фиксирует обработанные сообщения, чтобы повторная доставка
     * (at-least-once) не дублировала эффект. Ключ дедупа — (consumer, event_id).
     */
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('consumer');
            $table->string('event_id');
            $table->timestamp('processed_at');

            $table->unique(['consumer', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
