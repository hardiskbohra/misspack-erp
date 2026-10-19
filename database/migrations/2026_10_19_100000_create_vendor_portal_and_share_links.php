<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('portal_enabled')->default(false)->after('status');
            $table->timestamp('portal_enabled_at')->nullable()->after('portal_enabled');
            $table->timestamp('portal_last_shared_at')->nullable()->after('portal_enabled_at');
        });

        Schema::create('vendor_portal_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->nullable()->index();
            $table->string('mobile', 40)->nullable();
            $table->string('password');
            $table->boolean('portal_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
            $table->index(['vendor_id', 'is_active'], 'vendor_portal_users_vendor_active_idx');
        });

        Schema::create('vendor_collaboration_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->text('message');
            $table->date('expected_date')->nullable();
            $table->string('author_type', 20); // vendor, staff, secure_link
            $table->foreignId('vendor_portal_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_share_link_id')->nullable();
            $table->timestamp('read_by_office_at')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'created_at'], 'vendor_updates_vendor_created_idx');
            $table->index(['purchase_invoice_id', 'created_at'], 'vendor_updates_purchase_created_idx');
        });

        Schema::create('vendor_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->string('label');
            $table->string('resource_type', 30)->default('workspace');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('permissions');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->string('last_viewed_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vendor_id', 'revoked_at'], 'vendor_shares_vendor_revoked_idx');
            $table->index('expires_at');
        });

        Schema::table('vendor_collaboration_updates', function (Blueprint $table) {
            $table->foreign('vendor_share_link_id', 'vendor_updates_share_fk')
                ->references('id')->on('vendor_share_links')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_collaboration_updates');
        Schema::dropIfExists('vendor_share_links');
        Schema::dropIfExists('vendor_portal_users');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['portal_enabled', 'portal_enabled_at', 'portal_last_shared_at']);
        });
    }
};
