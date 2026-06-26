<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich "content documents": an ApiDocumentVersion can now hold inline authored
 * text (HTML / Markdown / plain text) instead of — or in addition to — a stored
 * file or external URL. The agent sends text only; the CRM renders + exports it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_document_versions', function (Blueprint $table) {
            // 'html' | 'markdown' | 'plaintext' — null for file/url versions.
            $table->string('content_format', 16)->nullable()->after('upload_source');
            // The raw authored content (LONGTEXT). Null for file/url versions.
            $table->longText('content_body')->nullable()->after('content_format');
        });

        Schema::table('api_documents', function (Blueprint $table) {
            // Fast flag so the UI/API can list content docs without joining versions.
            $table->boolean('is_content_doc')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('api_document_versions', function (Blueprint $table) {
            $table->dropColumn(['content_format', 'content_body']);
        });

        Schema::table('api_documents', function (Blueprint $table) {
            $table->dropColumn('is_content_doc');
        });
    }
};
