<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalises legacy `users.roles` JSON values to a single `admin`
     * role going forward. The Symfony app stored `ROLE_ADMIN` /
     * `ROLE_EDITOR` (and sometimes the lowercased `editor` post-cutover);
     * the new admin uses one role plus the `is_super` flag (S-10) to
     * cover the elevated capabilities.
     *
     * For each row:
     * - any `ROLE_ADMIN`, `admin`, `ROLE_EDITOR`, or `editor` value
     *   collapses to `admin`
     * - other values pass through untouched (keeps room for unforeseen
     *   legacy variants without clobbering them)
     * - duplicates are deduped, ordering preserved.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'roles'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = is_string($row->roles) ? json_decode($row->roles, true) : $row->roles;

                    if (! is_array($raw)) {
                        continue;
                    }

                    $normalised = [];
                    foreach ($raw as $value) {
                        if (! is_string($value)) {
                            continue;
                        }

                        $mapped = match ($value) {
                            'ROLE_ADMIN', 'admin', 'ROLE_EDITOR', 'editor' => 'admin',
                            default => $value,
                        };

                        if (! in_array($mapped, $normalised, true)) {
                            $normalised[] = $mapped;
                        }
                    }

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['roles' => json_encode($normalised)]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: the original distinction between ROLE_ADMIN and
        // ROLE_EDITOR is collapsed deliberately and is_super (S-10)
        // captures the elevated split going forward. Rolling back this
        // migration would not restore the lost role values.
    }
};
