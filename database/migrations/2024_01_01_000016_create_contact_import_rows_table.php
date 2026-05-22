<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_import_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->enum('status', ['pending', 'imported', 'skipped', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_import_rows');
    }
};
