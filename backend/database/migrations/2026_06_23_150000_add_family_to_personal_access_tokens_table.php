<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Семья связывает access+refresh одной сессии: ротация сохраняет family,
            // а отзыв при logout/переиспользовании гасит её целиком.
            $table->uuid('family')->nullable()->after('abilities')->index();
            // Отметка, что refresh уже ротирован: повторное предъявление = переиспользование.
            $table->timestamp('consumed_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['family']);
            $table->dropColumn(['family', 'consumed_at']);
        });
    }
};
