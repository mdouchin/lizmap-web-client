<?php

/**
 * Sunburst plot: values are always aggregated by distinct (Z, X) values.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

use LizmapDataviz\Aggregation\GroupIndex;

/**
 * The X field gives the labels, the Z field of the first trace gives
 * the parents. A root node contains all the parents.
 */
class SunburstPlot extends AbstractPlot
{
    public const TYPE = 'sunburst';

    /** Label of the root node */
    public const ROOT = 'Total';

    protected const AGGREGATION_MODE = self::AGGREGATION_ALWAYS;

    protected const FEATURE_COLORS = self::FEATURE_COLORS_MARKER_COLORS;

    protected const X_PROPERTY = 'labels';

    protected const Y_PROPERTY = 'values';

    protected const Z_PROPERTY = 'parents';

    protected function getTraceTemplate(): array
    {
        $template = array(
            'type' => 'sunburst',
            'name' => '',
            'values' => array(),
            'labels' => array(),
            'parents' => array(),
            'branchvalues' => 'total',
            'hovertemplate' => '%{label}<br>%{value:.1f}<br>%{percentEntry:,.0%}',
            'texttemplate' => '%{value:.1f}',
            'opacity' => null,
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
        $layout['margin']['b'] = 10;

        return $layout;
    }

    /**
     * The values are aggregated by (parent, label).
     */
    protected function getGroup(object $properties, mixed $x): mixed
    {
        $parentField = $this->getParentField();
        if ($parentField === null) {
            return array(null, $x);
        }

        return array($properties->{$parentField} ?? null, $x);
    }

    /**
     * Set the nodes: the root, the parents and the groups.
     *
     * The value of a parent is the sum of the values of its children,
     * the value of the root is the sum of all the values.
     * Groups without parent are attached to the root.
     *
     * As a label can be used by several parents, each node has a unique id.
     */
    protected function setAggregatedValues(array &$trace, array $groups, array $values, ?array $colors): ?array
    {
        $root = self::ROOT;

        $parentIndex = new GroupIndex();
        $parentNodes = array();
        $childIds = array();
        $childLabels = array();
        $childParents = array();
        $total = 0;
        foreach ($groups as $i => list($parent, $label)) {
            $value = is_numeric($values[$i]) ? $values[$i] + 0 : 0;
            $total += $value;

            $parentId = $root;
            if ($parent !== null && $parent !== '') {
                $p = $parentIndex->positionOf($parent);
                if (!isset($parentNodes[$p])) {
                    $parentNodes[$p] = array(
                        'id' => $root.'/'.$p,
                        'label' => $parent,
                        'value' => 0,
                        'color' => null,
                    );
                }
                $parentNodes[$p]['value'] += $value;
                if ($colors !== null && $parentNodes[$p]['color'] === null) {
                    $parentNodes[$p]['color'] = $colors[$i];
                }
                $parentId = $parentNodes[$p]['id'];
            }
            $childIds[] = $parentId.'/'.$i;
            $childLabels[] = $label;
            $childParents[] = $parentId;
        }

        // ids are only used by Plotly to build the hierarchy
        $ids = array($root);
        $labels = array($root);
        $nodeValues = array($total);
        $parents = array('');
        $nodeColors = array('white');
        foreach ($parentNodes as $node) {
            $ids[] = $node['id'];
            $labels[] = $node['label'];
            $nodeValues[] = $node['value'];
            $parents[] = $root;
            $nodeColors[] = $node['color'];
        }

        $trace['ids'] = array_merge($ids, $childIds);
        $trace[self::X_PROPERTY] = array_merge($labels, $childLabels);
        $trace[self::Y_PROPERTY] = array_merge($nodeValues, $values);
        $trace[self::Z_PROPERTY] = array_merge($parents, $childParents);

        if ($colors === null) {
            return null;
        }

        return array_merge($nodeColors, $colors);
    }

    /**
     * @return null|string the parent field: the Z field of the first trace
     */
    private function getParentField(): ?string
    {
        return $this->zFields[0] ?? null;
    }
}
