<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Отметка времени доставки приглашения подписанту. Делает поэтапную рассылку идемпотентной:
     * приглашение текущему по очереди уходит ровно один раз, даже если событие пришло повторно
     * или несколько событий (sent + signed) указали на одного участника.
     */
    public function up(): void
    {
        Schema::table('document_parties', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_parties', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
