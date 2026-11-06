<?php

/**
 * Bar plot, vertical or horizontal.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

class BarPlot extends AbstractPlot
{
    public const TYPE = 'bar';

    protected const FEATURE_COLORS = self::FEATURE_COLORS_MARKER_COLOR;

    protected function getTraceTemplate(): array
    {
        return array(
            'type' => 'bar',
            'name' => '',
            'y' => array(),
            'x' => array(),
            'ids' => null,
            'text' => array(),
            'marker' => array(
                'color' => 'orange',
                'colorscale' => null,
                'showscale' => false,
                'reversescale' => false,
                'colorbar' => array(
                    'len' => '0.8',
                ),
                'line' => array(
                    'color' => null,
                    'width' => null,
                ),
            ),
            'textinfo' => 'none',
            'orientation' => $this->horizontal ? 'h' : 'v',
        );
    }

    protected function customizeLayout(array $layout): array
    {
        // No axis title
        if (count($this->yFields) > 1 && $this->stacked) {
            $layout['barmode'] = 'stack';
        }

        return $layout;
    }
}
