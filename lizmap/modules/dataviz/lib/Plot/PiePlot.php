<?php

/**
 * Pie plot: values are always aggregated by distinct X values.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

class PiePlot extends AbstractPlot
{
    public const TYPE = 'pie';

    protected const AGGREGATION_MODE = self::AGGREGATION_ALWAYS;

    protected const FEATURE_COLORS = self::FEATURE_COLORS_MARKER_COLORS;

    protected const SORT_BY_X = true;

    protected const X_PROPERTY = 'labels';

    protected const Y_PROPERTY = 'values';

    protected function getTraceTemplate(): array
    {
        $template = array(
            'type' => 'pie',
            'name' => '',
            'values' => array(),
            'labels' => array(),
            'hoverinfo' => 'label+value+percent',
            'hovertemplate' => '%{label}<br>%{value:.1f}<br>%{percent:,.0%}',
            'textinfo' => 'value',
            'texttemplate' => '%{value:.1f}',
            'insidetextorientation' => 'horizontal',
            'opacity' => null,
            'hole' => '0.4',
            'automargin' => true,
            'sort' => false, // slices will be sort by X data
        );
        if ($this->theme == 'dark') {
            $template['outsidetextfont'] = array('color' => 'white');
        }

        return $template;
    }

    protected function customizeLayout(array $layout): array
    {
        // No axis title
        $layout['legend']['y'] = '-5';
        if (!$this->displayLegend) {
            $layout['margin']['b'] = 10;
        }

        return $layout;
    }
}
