<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_party_id')->constrained('document_parties')->cascadeOnDelete();
            $table->string('signature_hash', 64);
            $table->json('signed_payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');

            $table->unique('document_party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
