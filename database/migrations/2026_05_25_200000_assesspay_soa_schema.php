<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'portal_user_id')) {
                $table->string('portal_user_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('students', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('students', 'status')) {
                $table->string('status')->default('active')->after('email');
            }
        });

        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('account_number')->unique();
            $table->string('currency', 3)->default('PHP');
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'status']);
        });

        Schema::create('tuition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('school_year', 20);
            $table->string('term', 20)->nullable();
            $table->string('description');
            $table->decimal('tuition_amount', 12, 2)->default(0);
            $table->decimal('misc_amount', 12, 2)->default(0);
            $table->decimal('other_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'processing', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'school_year', 'status']);
        });

        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_assessed', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamps();
            $table->unique('billing_account_id');
            $table->index(['student_id', 'current_balance']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'billing_account_id')) {
                    $table->foreignId('billing_account_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'tuition_record_id')) {
                    $table->foreignId('tuition_record_id')->nullable()->after('billing_account_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'payment_method_id')) {
                    $table->foreignId('payment_method_id')->nullable()->after('method')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('payments', 'status')) {
                    $table->enum('status', ['pending', 'processing', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'])->default('pending')->after('amount');
                }
                if (! Schema::hasColumn('payments', 'submitted_by_portal_id')) {
                    $table->string('submitted_by_portal_id')->nullable();
                }
                if (! Schema::hasColumn('payments', 'confirmed_by_portal_id')) {
                    $table->string('confirmed_by_portal_id')->nullable();
                }
                if (! Schema::hasColumn('payments', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable();
                }
                if (! Schema::hasColumn('payments', 'correlation_id')) {
                    $table->uuid('correlation_id')->nullable();
                }
                if (! Schema::hasColumn('payments', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        } else {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('billing_account_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('tuition_record_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
                $table->string('receipt_number')->nullable()->unique();
                $table->decimal('amount', 12, 2);
                $table->enum('status', ['pending', 'processing', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'])->default('pending');
                $table->string('method')->nullable();
                $table->string('reference_number')->nullable();
                $table->string('submitted_by_portal_id')->nullable();
                $table->string('confirmed_by_portal_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->uuid('correlation_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['student_id', 'status']);
            });
        }

        Schema::create('official_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('issued_by_portal_id')->nullable();
            $table->timestamp('issued_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('running_balance', 12, 2)->default(0);
            $table->string('reference')->nullable();
            $table->string('performed_by_portal_id')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('actor_portal_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'created_at']);
        });

        if (! Schema::hasColumn('notifications', 'type')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (! Schema::hasColumn('notifications', 'portal_user_id')) {
                    $table->string('portal_user_id')->nullable();
                }
                if (! Schema::hasColumn('notifications', 'payload')) {
                    $table->json('payload')->nullable();
                }
                if (! Schema::hasColumn('notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable();
                }
            });
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->default('assesspay');
            $table->string('event');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_portal_id')->nullable();
            $table->string('causer_role')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('event_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_name');
            $table->string('source_service');
            $table->json('payload');
            $table->string('schema_version')->default('1.0');
            $table->uuid('correlation_id')->nullable();
            $table->string('signature')->nullable();
            $table->string('nonce')->nullable();
            $table->unsignedBigInteger('timestamp');
            $table->enum('status', ['pending', 'published', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('portal_user_sync', function (Blueprint $table) {
            $table->id();
            $table->string('portal_user_id')->unique();
            $table->string('email')->index();
            $table->string('name');
            $table->enum('role', ['cashier', 'student', 'admin']);
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_user_sync');
        Schema::dropIfExists('event_outbox');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('payment_audit_logs');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('official_receipts');
        Schema::dropIfExists('balances');
        Schema::dropIfExists('tuition_records');
        Schema::dropIfExists('billing_accounts');
        Schema::dropIfExists('payment_methods');
    }
};
