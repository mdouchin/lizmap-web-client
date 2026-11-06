<?php

/**
 * 2D histogram.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

class Histogram2dPlot extends AbstractPlot
{
    public const TYPE = 'histogram2d';

    protected const AGGREGATION_MODE = self::AGGREGATION_NEVER;

    protected function getTraceTemplate(): array
    {
        return array(
            'type' => 'histogram2d',
            'name' => '',
            'x' => array(),
            'y' => array(),
            'colorscale' => null,
            'opacity' => null,
        );
    }
}
