<?php

/**
 * Aggregate the values of one trace by group.
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
 * Online (one pass) aggregation of the values of a trace per group.
 *
 * Only the state required by the configured function is kept, so the memory
 * used is proportional to the number of groups, except for the median which
 * needs to keep every numeric value.
 *
 * Except for count, first and last, non numeric values are ignored.
 */
final class TraceAggregator
{
    private string $function;

    /**
     * Main state per group: sum, count, min, max, first or last value.
     *
     * @var array<int, mixed>
     */
    private array $values = array();

    /**
     * Number of numeric values per group (avg, stddev).
     *
     * @var array<int, int>
     */
    private array $counts = array();

    /**
     * Running mean per group (stddev, Welford algorithm).
     *
     * @var array<int, float>
     */
    private array $means = array();

    /**
     * Sum of squares of differences from the mean per group (stddev).
     *
     * @var array<int, float>
     */
    private array $m2 = array();

    /**
     * Numeric values per group (median only).
     *
     * @var array<int, list<float|int>>
     */
    private array $samples = array();

    /**
     * First non empty color per group.
     *
     * @var array<int, mixed>
     */
    private array $colors = array();

    /**
     * @param string $function one of the AggregateFunction constants
     */
    public function __construct(string $function)
    {
        if (!in_array($function, AggregateFunction::ALL, true)) {
            throw new \InvalidArgumentException('Unknown aggregate function "'.$function.'"');
        }
        $this->function = $function;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * Add a feature value to a group.
     *
     * @param int   $group the group position given by the GroupIndex
     * @param mixed $value the feature value (Y)
     * @param mixed $color the feature color, if any
     */
    public function add(int $group, mixed $value, mixed $color = null): void
    {
        if ($color !== null && $color !== '' && !isset($this->colors[$group])) {
            $this->colors[$group] = $color;
        }

        switch ($this->function) {
            case AggregateFunction::COUNT:
                $this->values[$group] = ($this->values[$group] ?? 0) + 1;

                return;

            case AggregateFunction::FIRST:
                if (!array_key_exists($group, $this->values)) {
                    $this->values[$group] = $value;
                }

                return;

            case AggregateFunction::LAST:
                $this->values[$group] = $value;

                return;
        }

        $number = self::toNumber($value);
        if ($number === null) {
            return;
        }

        switch ($this->function) {
            case AggregateFunction::SUM:
                $this->values[$group] = ($this->values[$group] ?? 0) + $number;

                break;

            case AggregateFunction::MIN:
                if (!isset($this->values[$group]) || $number < $this->values[$group]) {
                    $this->values[$group] = $number;
                }

                break;

            case AggregateFunction::MAX:
                if (!isset($this->values[$group]) || $number > $this->values[$group]) {
                    $this->values[$group] = $number;
                }

                break;

            case AggregateFunction::AVG:
                $this->values[$group] = ($this->values[$group] ?? 0) + $number;
                $this->counts[$group] = ($this->counts[$group] ?? 0) + 1;

                break;

            case AggregateFunction::STDDEV:
                // Welford online algorithm
                $count = ($this->counts[$group] ?? 0) + 1;
                $mean = $this->means[$group] ?? 0.0;
                $delta = $number - $mean;
                $mean += $delta / $count;
                $this->counts[$group] = $count;
                $this->means[$group] = $mean;
                $this->m2[$group] = ($this->m2[$group] ?? 0.0) + $delta * ($number - $mean);

                break;

            case AggregateFunction::MEDIAN:
                $this->samples[$group][] = $number;

                break;
        }
    }

    /**
     * Get the aggregated value of each group.
     *
     * @param int $groupCount the total number of groups
     *
     * @return list<mixed> the values, aligned on the group positions
     */
    public function results(int $groupCount): array
    {
        $results = array();
        for ($group = 0; $group < $groupCount; ++$group) {
            $results[] = $this->result($group);
        }

        return $results;
    }

    /**
     * Get the color of each group.
     *
     * @param int $groupCount the total number of groups
     *
     * @return null|list<mixed> the colors aligned on the group positions
     *                          (null for a group without color), or null
     *                          if no group has a color
     */
    public function colors(int $groupCount): ?array
    {
        if (count($this->colors) === 0) {
            return null;
        }
        $colors = array();
        for ($group = 0; $group < $groupCount; ++$group) {
            $colors[] = $this->colors[$group] ?? null;
        }

        return $colors;
    }

    private function result(int $group): mixed
    {
        switch ($this->function) {
            case AggregateFunction::COUNT:
            case AggregateFunction::SUM:
                return $this->values[$group] ?? 0;

            case AggregateFunction::AVG:
                if (empty($this->counts[$group])) {
                    return null;
                }

                return $this->values[$group] / $this->counts[$group];

            case AggregateFunction::STDDEV:
                // Population standard deviation
                if (empty($this->counts[$group])) {
                    return null;
                }

                return sqrt($this->m2[$group] / $this->counts[$group]);

            case AggregateFunction::MEDIAN:
                return self::median($this->samples[$group] ?? array());

            default:
                // min, max, first, last
                return $this->values[$group] ?? null;
        }
    }

    /**
     * @param list<float|int> $values
     */
    private static function median(array $values): null|float|int
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Convert a value to a number, if possible.
     */
    private static function toNumber(mixed $value): null|float|int
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return null;
    }
}
