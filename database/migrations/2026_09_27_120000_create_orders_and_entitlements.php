<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One purchase: a set of subjects of one exam, paid online (Paystack) or by bank transfer.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40)->unique();          // sent to Paystack, and quoted on bank transfers
            $table->string('method', 20);                        // paystack, bank_transfer
            $table->string('status', 24)->default('pending')->index(); // pending, awaiting_confirmation, paid, failed, rejected, cancelled
            $table->foreignId('exam_id')->constrained();
            $table->boolean('is_bundle')->default(false);
            $table->unsignedInteger('amount');                   // whole naira, worked out on the server
            $table->string('currency', 3)->default('NGN');
            $table->unsignedInteger('access_days');              // fixed when the order is made, so a later price change cannot alter it
            $table->string('payer_name')->nullable();            // bank transfer: name on the sending account
            $table->string('proof_path')->nullable();            // bank transfer: screenshot of the receipt
            $table->text('note')->nullable();                    // the student's message with a bank transfer
            $table->text('decision_note')->nullable();           // why an admin did not confirm a transfer
            $table->json('gateway_data')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('submitted_at')->nullable();        // bank transfer: when the student said they had paid
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->unsignedInteger('list_price');               // the subject's own price at the time (for the receipt)
            $table->unique(['order_id', 'subject_id']);
        });

        // What a student can use in full. Renewing pushes expires_at out; one row per student, exam and subject.
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'exam_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
