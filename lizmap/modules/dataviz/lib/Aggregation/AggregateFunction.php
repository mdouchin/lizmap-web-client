<?php

/**
 * Aggregate functions supported by the dataviz plots.
 *
 * @author    3liz
 * @copyright 2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Aggregation;

final class AggregateFunction
{
    public const SUM = 'sum';
    public const COUNT = 'count';
    public const AVG = 'avg';
    public const MIN = 'min';
    public const MAX = 'max';
    public const FIRST = 'first';
    public const LAST = 'last';
    public const STDDEV = 'stddev';
    public const MEDIAN = 'median';

    public const ALL = array(
        self::SUM,
        self::COUNT,
        self::AVG,
        self::MIN,
        self::MAX,
        self::FIRST,
        self::LAST,
        self::STDDEV,
        self::MEDIAN,
    );

    /**
     * Normalize the aggregation given in the plot configuration.
     *
     * @param mixed $value the aggregation from the plot configuration
     *
     * @return null|string one of the supported functions, or null when
     *                     no (or an unknown) aggregation is configured
     *                     ('', 'no', 'none', null...)
     */
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($value === 'mean') {
            $value = self::AVG;
        }

        return in_array($value, self::ALL, true) ? $value : null;
    }
}
