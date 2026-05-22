<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['opportunity_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_contact');
    }
};
