<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for security-relevant operational events (forced global
 * logout at cutover, emergency token revocation, etc.). One row per
 * event; the table is append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            // Short slug identifying the kind of event, e.g.
            // "symfony_cutover_forced_logout". Indexed for
            // idempotency lookups and audit filtering.
            $table->string('event');
            // Human-readable justification recorded at write time.
            $table->string('reason');
            // Number of rows affected by the event (e.g. tokens
            // revoked). Nullable because some events have no count.
            $table->unsignedInteger('affected_count')->nullable();
            // Arbitrary structured context (cutover timestamp, operator
            // name, commit sha). Kept as JSON so we can add fields
            // without schema churn.
            $table->json('metadata')->nullable();
            // Wall-clock time the event actually occurred — distinct
            // from created_at so backdated audit rows remain truthful.
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index('event');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
