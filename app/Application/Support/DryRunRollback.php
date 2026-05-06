<?php

declare(strict_types=1);

namespace App\Application\Support;

use RuntimeException;

/**
 * Internal sentinel: wrapping each dry-run sub-job in a transaction and
 * forcing a rollback by throwing this is the cleanest way to keep
 * persistent DB state pristine without disabling foreign-key checks.
 */
final class DryRunRollback extends RuntimeException {}
