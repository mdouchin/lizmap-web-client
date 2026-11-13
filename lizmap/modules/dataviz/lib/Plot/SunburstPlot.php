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
use LizmapDataviz\Json\JsonColumn;

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

    /** The groups are processed to build the hierarchy: they are given as arrays */
    protected const AGGREGATED_COLUMNS = false;

    protected const FEATURE_COLORS = self::FEATURE_COLORS_MARKER_COLORS;

    protected const X_PROPERTY = 'labels';

    protected const Y_PROPERTY = 'values';

    protected const Z_PROPERTY = 'parents';

    /**
     * Get the trace template of the plot type.
     *
     * @return array the Plotly trace properties, with empty data
     */
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
        // White text outside the nodes for the dark theme
        if ($this->theme == 'dark') {
            $template['outsidetextfont'] = array('color' => 'white');
        }

        return $template;
    }

    /**
     * Customize the layout for the plot type.
     *
     * No axis title is displayed.
     *
     * @param array $layout the default layout
     *
     * @return array the layout of the plot type
     */
    protected function customizeLayout(array $layout): array
    {
        // Legend below the plot and small bottom margin
        $layout['legend']['y'] = '-5';
        $layout['margin']['b'] = 10;

        return $layout;
    }

    /**
     * Get the group of a feature: its parent and its label.
     *
     * The same label can be used by several parents, so the values
     * are aggregated by (parent, label).
     *
     * @param object $properties the feature properties
     * @param mixed  $x          the feature X value (label), not null
     *
     * @return array the parent (null if none) and the label
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
     * Get the fields grouping the features in the database aggregation:
     * the parent field if any, and the label field (X).
     *
     * @return list<string> the group fields
     */
    protected function getGroupFields(): array
    {
        $parentField = $this->getParentField();
        if ($parentField === null) {
            return array($this->xFields[0]);
        }

        return array($parentField, $this->xFields[0]);
    }

    /**
     * Get the group (parent, label) from the values of the group fields.
     *
     * @param list<mixed> $values the values of the fields given by getGroupFields()
     *
     * @return array the parent (null if none) and the label
     */
    protected function getGroupFromValues(array $values): mixed
    {
        if (count($values) === 1) {
            return array(null, $values[0]);
        }

        return array($values[0], $values[1]);
    }

    /**
     * Set the nodes: the root, the parents and the groups.
     *
     * The value of a parent is the sum of the values of its children,
     * the value of the root is the sum of all the values.
     * Groups without parent are attached to the root.
     *
     * As a label can be used by several parents, each node has a unique id.
     *
     * The values are given as arrays, as AGGREGATED_COLUMNS is false.
     *
     * @param array                  $trace  the sunburst trace
     * @param JsonColumn|list<array> $groups the groups: [parent, label]
     * @param JsonColumn|list<mixed> $values the aggregated value of each group
     * @param null|array|JsonColumn  $colors the color of each group, null if none
     *
     * @return null|array the colors of all the nodes, null if none
     */
    protected function setAggregatedValues(array &$trace, array|JsonColumn $groups, array|JsonColumn $values, null|array|JsonColumn $colors): ?array
    {
        $root = self::ROOT;

        // Decode the columns, if any, to process the values
        $groups = $groups instanceof JsonColumn ? $groups->jsonSerialize() : $groups;
        $values = $values instanceof JsonColumn ? $values->jsonSerialize() : $values;
        $colors = $colors instanceof JsonColumn ? $colors->jsonSerialize() : $colors;

        // Build the parent nodes from the groups. A parent has the sum
        // of the values of its children, and the color of its first child.

        $parentIndex = new GroupIndex();
        $parentNodes = array();
        $childIds = array();
        $childLabels = array();
        $childParents = array();
        $total = 0;
        foreach ($groups as $i => list($parent, $label)) {
            $value = is_numeric($values[$i]) ? $values[$i] + 0 : 0;
            $total += $value;

            // Groups without parent are attached to the root
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
            // The group position makes the child id unique
            $childIds[] = $parentId.'/'.$i;
            $childLabels[] = $label;
            $childParents[] = $parentId;
        }

        // The nodes: the root first, then the parents, then the groups.
        // The ids are only used by Plotly to build the hierarchy.
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

        // Set the nodes in the trace
        $trace['ids'] = array_merge($ids, $childIds);
        $trace[self::X_PROPERTY] = array_merge($labels, $childLabels);
        $trace[self::Y_PROPERTY] = array_merge($nodeValues, $values);
        $trace[self::Z_PROPERTY] = array_merge($parents, $childParents);

        // The colors of the nodes, in the same order
        if ($colors === null) {
            return null;
        }

        return array_merge($nodeColors, $colors);
    }

    /**
     * Get the field containing the parent of the features.
     *
     * @return null|string the parent field: the Z field of the first trace
     */
    private function getParentField(): ?string
    {
        return $this->zFields[0] ?? null;
    }
}
