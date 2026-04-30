<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Support;

class SeededShuffler
{
    /**
     * Deterministically shuffle `$items` using `$seed`.
     *
     * Identical seeds produce identical orderings over the same input.
     * Uses `mt_srand` + `shuffle` for Symfony parity. The seed state is
     * set and consumed in the same call so the function is effectively
     * stateless from the caller's perspective.
     *
     * @template TValue
     *
     * @param  array<array-key, TValue>  $items
     * @return array<int, TValue>
     */
    public function shuffle(array $items, int $seed): array
    {
        $values = array_values($items);

        if ($values === []) {
            return $values;
        }

        mt_srand($seed);
        shuffle($values);
        // Re-seed with the current microtime so downstream mt_rand() calls
        // are not trivially predictable after we consumed a fixed seed.
        mt_srand();

        return $values;
    }
}
