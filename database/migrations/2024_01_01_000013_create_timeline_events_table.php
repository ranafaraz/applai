<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('timelineable_id');
            $table->string('timelineable_type');
            $table->string('event_type');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['timelineable_id', 'timelineable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_events');
    }
};
