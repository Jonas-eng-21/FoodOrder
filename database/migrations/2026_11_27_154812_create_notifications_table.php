<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('event_key');
            $table->string('audience');
            $table->string('message');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'event_key', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
