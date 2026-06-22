<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('role')->default('signer');
            $table->unsignedSmallInteger('signing_order')->default(1);
            $table->string('status')->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_parties');
    }
};
