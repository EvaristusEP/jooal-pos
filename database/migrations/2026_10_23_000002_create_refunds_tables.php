<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 40)->unique();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('refund_type', 20);
            $table->decimal('amount', 10, 2);
            $table->string('status', 30)->default('recorded');
            $table->string('manual_method', 30);
            $table->text('reason');
            $table->string('idempotency_key', 128)->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('recorded_at');
            $table->timestamp('manually_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->onDelete('restrict');
            $table->foreignId('transaction_item_id')->constrained('transaction_items')->onDelete('restrict');
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['refund_id', 'transaction_item_id']);
            $table->index('transaction_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};
