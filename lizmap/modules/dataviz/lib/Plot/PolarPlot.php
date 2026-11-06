<?php

/**
 * Polar plot.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

class PolarPlot extends AbstractPlot
{
    public const TYPE = 'polar';

    protected const X_PROPERTY = 'r';

    protected const Y_PROPERTY = 't';

    protected function getTraceTemplate(): array
    {
        return array(
            'type' => 'scattergl',
            'name' => '',
            'r' => array(),
            't' => array(),
            'textinfo' => 'r+t',
            'mode' => 'markers',
            'hoverinfo' => 'label+value+percent',
            'marker' => array(
                'color' => 'orange',
            ),
            'opacity' => null,
        );
    }
}
