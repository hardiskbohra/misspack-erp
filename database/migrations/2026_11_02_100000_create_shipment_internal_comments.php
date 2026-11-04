<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['shipment_id', 'id']);
        });

        Schema::create('shipment_comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_comment_id')->constrained('shipment_comments')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Dropping populated tables would silently lose the team's discussion
        // and strand private files. Stop writers and back up/export both the
        // database and private storage before an intentional removal.
        if ((Schema::hasTable('shipment_comments') && DB::table('shipment_comments')->exists())
            || (Schema::hasTable('shipment_comment_attachments') && DB::table('shipment_comment_attachments')->exists())) {
            throw new RuntimeException('Cannot roll back shipment comments while they contain data. Back up and remove comments and private files first.');
        }

        Schema::dropIfExists('shipment_comment_attachments');
        Schema::dropIfExists('shipment_comments');
    }
};
