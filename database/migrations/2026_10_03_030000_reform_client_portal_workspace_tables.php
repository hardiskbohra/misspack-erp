<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['client_portal_documents', 'client_portal_invoices'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'storage_disk')) {
                Schema::table($tableName, function (Blueprint $table) {
                    // Existing files stay readable from the public disk. New portal uploads
                    // explicitly select the private local disk in their controllers.
                    $table->string('storage_disk', 50)->default('public');
                });
            }
        }

        if (Schema::hasTable('client_portal_documents') && ! Schema::hasColumn('client_portal_documents', 'project_product_id')) {
            Schema::table('client_portal_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('project_product_id')->nullable()->index();
            });
        }

        if (! Schema::hasTable('client_portal_conversations')) {
            Schema::create('client_portal_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('client_portal_user_id')->nullable()
                    ->constrained('client_portal_users')->nullOnDelete();
                $table->string('subject', 180);
                $table->string('category', 40)->default('general');
                $table->string('priority', 20)->default('normal');
                $table->string('status', 30)->default('open');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'status', 'updated_at']);
                $table->index(['client_portal_user_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('client_portal_messages')) {
            Schema::create('client_portal_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')
                    ->constrained('client_portal_conversations')->cascadeOnDelete();
                $table->string('sender_type', 20); // client | staff
                $table->unsignedBigInteger('sender_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'sender_type', 'read_at']);
                $table->index(['sender_type', 'sender_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_messages');
        Schema::dropIfExists('client_portal_conversations');

        if (Schema::hasTable('client_portal_documents') && Schema::hasColumn('client_portal_documents', 'project_product_id')) {
            Schema::table('client_portal_documents', function (Blueprint $table) {
                $table->dropIndex(['project_product_id']);
                $table->dropColumn('project_product_id');
            });
        }

        foreach (['client_portal_documents', 'client_portal_invoices'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'storage_disk')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('storage_disk');
                });
            }
        }
    }
};
