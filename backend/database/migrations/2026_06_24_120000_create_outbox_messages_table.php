<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            // event_id уникален — ключ дедупликации при публикации (at-least-once у брокера).
            $table->uuid('event_id')->unique();
            $table->string('event_type');           // document.signed
            $table->string('routing_key');          // document.signed.v1
            $table->string('aggregate_type')->nullable();
            $table->string('aggregate_id')->nullable();
            $table->json('payload');                // полный конверт события (что уходит в брокер)
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            // available_at — когда строку можно публиковать (для backoff при ретраях).
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('published_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // Publisher выбирает «готовые к отправке» — этим индексом.
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
