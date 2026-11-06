<?php

/**
 * Index of the distinct group values (X values) of a plot.
 *
 * @author    3liz
 * @copyright 2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Aggregation;

/**
 * Give a stable position to each distinct value, in order of first appearance.
 *
 * The index is shared by all the traces of a plot, so that the aggregated
 * values of every trace are aligned on the same X values.
 *
 * Values are not used directly as PHP array keys: it would cast floats to int
 * ("1.5" and "1.7" in the same group) and numeric strings to int.
 */
final class GroupIndex
{
    /** @var array<string, int> */
    private array $positions = array();

    /** @var list<mixed> */
    private array $values = array();

    /**
     * Get the position of the given value, adding it if not yet known.
     */
    public function positionOf(mixed $value): int
    {
        if (is_string($value)) {
            $key = 's'.$value;
        } elseif (is_int($value)) {
            $key = 'i'.$value;
        } else {
            $key = 'o'.json_encode($value);
        }

        $position = $this->positions[$key] ?? null;
        if ($position === null) {
            $position = count($this->values);
            $this->positions[$key] = $position;
            $this->values[] = $value;
        }

        return $position;
    }

    /**
     * @return list<mixed> the distinct values, in order of first appearance
     */
    public function values(): array
    {
        return $this->values;
    }

    public function count(): int
    {
        return count($this->values);
    }
}
