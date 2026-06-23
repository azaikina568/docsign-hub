<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
        });

        // Бэкофилл для уже существующих строк: без ULID уникальный индекс не наложить.
        foreach (DB::table('documents')->whereNull('ulid')->pluck('id') as $id) {
            DB::table('documents')->where('id', $id)->update(['ulid' => (string) Str::ulid()]);
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->ulid('ulid')->nullable(false)->change();
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
