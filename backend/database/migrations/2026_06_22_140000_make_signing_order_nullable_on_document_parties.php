<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // signing_order имеет смысл только для подписантов; у наблюдателей он null.
        Schema::table('document_parties', function (Blueprint $table) {
            $table->unsignedSmallInteger('signing_order')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_parties', function (Blueprint $table) {
            $table->unsignedSmallInteger('signing_order')->default(1)->change();
        });
    }
};
