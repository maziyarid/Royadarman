<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders (payment requests)
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('public_reference', 24)->unique();
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUlid('referral_request_id')->nullable()->constrained('referral_requests')->nullOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20)->index(); // visit_fee, service, package
            $table->string('status', 20)->default('draft')->index(); // draft, pending, paid, failed, cancelled, refunded
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('payment_method', 20)->nullable(); // ipg, wallet, manual
            $table->string('payment_gateway', 20)->nullable(); // zarinpal, idpay, etc.
            $table->string('gateway_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['patient_user_id', 'status']);
            $table->index(['appointment_id', 'status']);
        });

        // Order lines (individual items in an order)
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('type', 20)->index(); // visit_fee, service, adjustment
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total_price');
            $table->string('currency', 3)->default('IRR');
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Price snapshots (immutable price records)
        Schema::create('price_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('order_line_id')->constrained('order_lines')->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedBigInteger('base_price');
            $table->unsignedBigInteger('final_price');
            $table->string('currency', 3)->default('IRR');
            $table->json('pricing_context')->nullable();
            $table->timestamp('created_at');
        });

        // Payment intents (payment attempts)
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('gateway', 20)->index();
            $table->string('gateway_reference', 100)->unique();
            $table->string('status', 20)->default('pending')->index(); // pending, processing, succeeded, failed, cancelled
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_url')->nullable();
            $table->string('callback_url')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        // Payment transactions (actual payment records)
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_intent_id')->constrained('payment_intents')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_reference', 100)->unique();
            $table->string('transaction_type', 20)->index(); // authorization, capture, refund, chargeback
            $table->string('status', 20)->index(); // pending, completed, failed, reversed
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('source', 20)->nullable(); // patient, clinic, platform
            $table->string('destination', 20)->nullable(); // platform, clinic
            $table->json('gateway_data')->nullable();
            $table->timestamp('processed_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        // Payment webhooks (incoming notifications from payment gateways)
        Schema::create('payment_webhooks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->foreignUlid('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('gateway', 20)->index();
            $table->string('event_type', 50)->index();
            $table->string('signature', 255)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('received')->index(); // received, processed, failed, duplicate
            $table->string('failure_reason')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Refunds
        Schema::create('refunds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUlid('payment_transaction_id')->constrained('payment_transactions')->restrictOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 100)->index();
            $table->string('status', 20)->default('requested')->index(); // requested, processing, completed, failed, cancelled
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('gateway_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Ledger accounts (chart of accounts)
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique()->index();
            $table->string('name');
            $table->string('type', 20)->index(); // asset, liability, equity, revenue, expense
            $table->string('category', 50)->index(); // cash, receivable, payable, revenue, fee
            $table->string('currency', 3)->default('IRR');
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Ledger transactions (journal entries)
        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 50)->unique();
            $table->string('description');
            $table->timestamp('transaction_date');
            $table->timestamp('posted_at')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, posted, voided
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Ledger entries (individual lines in a transaction - double entry)
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
            $table->foreignUlid('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('entry_type', 10)->index(); // debit, credit
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('description')->nullable();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUlid('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignUlid('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('patient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->integer('sequence')->default(0);
            $table->timestamp('created_at');
            $table->index(['ledger_transaction_id', 'sequence']);
            $table->index(['ledger_account_id', 'created_at']);
        });

        // Settlement batches (payout runs)
        Schema::create('settlement_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('batch_reference', 50)->unique();
            $table->string('status', 20)->default('draft')->index(); // draft, processing, completed, failed
            $table->date('settlement_date');
            $table->unsignedBigInteger('total_amount');
            $table->string('currency', 3)->default('IRR');
            $table->integer('item_count')->default(0);
            $table->string('processed_by', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Settlement items (individual payouts in a batch)
        Schema::create('settlement_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('settlement_batch_id')->constrained('settlement_batches')->cascadeOnDelete();
            $table->foreignUlid('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignUlid('payout_account_id')->constrained('payout_accounts')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('type', 20)->index(); // visit_fee, service, adjustment
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('status', 20)->default('pending')->index(); // pending, paid, failed, reversed
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Reconciliation results
        Schema::create('reconciliation_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->date('reconciliation_date');
            $table->string('gateway', 20)->index();
            $table->string('status', 20)->default('pending')->index(); // pending, matched, discrepancy
            $table->unsignedBigInteger('expected_amount');
            $table->unsignedBigInteger('actual_amount');
            $table->unsignedBigInteger('discrepancy_amount')->default(0);
            $table->string('currency', 3)->default('IRR');
            $table->integer('transaction_count')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('discrepancy_count')->default(0);
            $table->text('notes')->nullable();
            $table->json('discrepancies')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['reconciliation_date', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_results');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('price_snapshots');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
