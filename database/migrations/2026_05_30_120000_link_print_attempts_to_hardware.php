<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.4 §6 Printers tab — link document_print_attempts to the
 * hardware_devices registry and add the two columns the safe-write
 * surfaces need:
 *
 *   - printer_hardware_id        soft FK to hardware_devices.id; the
 *                                seeder back-fills from printer_name →
 *                                asset_tag.
 *   - retry_of_attempt_id        soft FK to a sibling attempt; written
 *                                when the operator hits "Retry failed
 *                                job" on the same printer.
 *   - replacement_of_attempt_id  soft FK to a sibling attempt; written
 *                                when the operator reroutes a failed job
 *                                to a DIFFERENT printer. The original
 *                                row's status flips to 'rerouted' so the
 *                                chain stays traceable both ways.
 *
 * All three columns are nullable + soft-FK; no constraint so the
 * print-attempt history stays append-only even if a hardware row is
 * archived later. The D1 reprint flow (`is_reprint`) is untouched —
 * reprint and retry are operationally distinct writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_print_attempts', function (Blueprint $t) {
            $t->char('printer_hardware_id', 36)
                ->nullable()
                ->after('printer_name');

            $t->char('retry_of_attempt_id', 36)
                ->nullable()
                ->after('reprint_reason');

            $t->char('replacement_of_attempt_id', 36)
                ->nullable()
                ->after('retry_of_attempt_id');

            $t->index('printer_hardware_id', 'doc_print_attempts_hardware_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_print_attempts', function (Blueprint $t) {
            $t->dropIndex('doc_print_attempts_hardware_idx');
            $t->dropColumn([
                'printer_hardware_id',
                'retry_of_attempt_id',
                'replacement_of_attempt_id',
            ]);
        });
    }
};
