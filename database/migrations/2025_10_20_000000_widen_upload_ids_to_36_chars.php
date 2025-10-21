<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $uploadsTable = config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');
        $docsTable = config('vizra-adk.tables.cases_documents', 'cases_documents');
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop FK on upload_id to allow type change
            DB::statement("ALTER TABLE \"{$docsTable}\" DROP CONSTRAINT IF EXISTS \"{$docsTable}_upload_id_foreign\"");

            // Widen id on uploads table to accept UUID (36) and ULID (26)
            DB::statement("ALTER TABLE \"{$uploadsTable}\" ALTER COLUMN \"id\" TYPE varchar(36)");

            // Widen upload_id on documents table accordingly
            DB::statement("ALTER TABLE \"{$docsTable}\" ALTER COLUMN \"upload_id\" TYPE varchar(36)");

            // Recreate FK with ON DELETE SET NULL as in original migration
            DB::statement(
                "ALTER TABLE \"{$docsTable}\" ADD CONSTRAINT \"{$docsTable}_upload_id_foreign\" FOREIGN KEY (\"upload_id\") REFERENCES \"{$uploadsTable}\"(\"id\") ON DELETE SET NULL"
            );
        }
        // For other drivers, no-op for now. We can extend if needed.
    }

    public function down(): void
    {
        $uploadsTable = config('vizra-adk.tables.cases_documents_uploads', 'cases_documents_uploads');
        $docsTable = config('vizra-adk.tables.cases_documents', 'cases_documents');
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE \"{$docsTable}\" DROP CONSTRAINT IF EXISTS \"{$docsTable}_upload_id_foreign\"");
            DB::statement("ALTER TABLE \"{$uploadsTable}\" ALTER COLUMN \"id\" TYPE char(26)");
            DB::statement("ALTER TABLE \"{$docsTable}\" ALTER COLUMN \"upload_id\" TYPE char(26)");
            DB::statement(
                "ALTER TABLE \"{$docsTable}\" ADD CONSTRAINT \"{$docsTable}_upload_id_foreign\" FOREIGN KEY (\"upload_id\") REFERENCES \"{$uploadsTable}\"(\"id\") ON DELETE SET NULL"
            );
        }
    }
};

