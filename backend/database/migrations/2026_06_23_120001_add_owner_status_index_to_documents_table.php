<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Покрывает выборку списка документов владельца с фильтром по статусу и сортировкой по дате.
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['owner_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'status', 'created_at']);
        });
    }
};
