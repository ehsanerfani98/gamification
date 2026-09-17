<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// زنجیره مالی — فصل ۷-۳: پرداخت از طریق دروازه انتزاعی PaymentGateway
// و فاکتور پس از تأیید پرداخت صادر می‌شود.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->unsignedInteger('amount_irt');
            $table->string('gateway', 30)->default('fake');
            $table->string('reference')->nullable()->unique();
            $table->string('status', 20)->default('pending'); // pending | paid | failed
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('payment_id')->constrained();
            $table->string('number')->unique();
            $table->unsignedInteger('amount_irt');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
    }
};
