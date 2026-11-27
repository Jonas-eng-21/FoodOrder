<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'new_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
