<?php

/**
 * Base class of the dataviz plots.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

use Lizmap\Project\Project;
use LizmapDataviz\Aggregation\AggregateFunction;
use LizmapDataviz\Aggregation\GroupIndex;
use LizmapDataviz\Aggregation\TraceAggregator;
use LizmapDataviz\Json\JsonColumn;
use LizmapDataviz\Json\JsonStreamWriter;

/**
 * Build the Plotly data and layout of a plot from the layer features.
 *
 * The plot types only define their trace template and their specific
 * behaviours with the constants and methods below.
 *
 * The features are read one by one: the plot never keeps them in memory.
 * Depending on the aggregation, the traces contain either one value per
 * feature (raw traces) or one value per distinct X value (aggregated traces).
 */
abstract class AbstractPlot
{
    /** The values are never aggregated: the plot displays a distribution */
    protected const AGGREGATION_NEVER = 'never';

    /** The values are aggregated if an aggregation is configured */
    protected const AGGREGATION_OPTIONAL = 'optional';

    /** The values are always aggregated, with a sum by default */
    protected const AGGREGATION_ALWAYS = 'always';

    /** No feature colors */
    protected const FEATURE_COLORS_NONE = null;

    /** The feature colors are set in trace.marker.color */
    protected const FEATURE_COLORS_MARKER_COLOR = 'color';

    /** The feature colors are set in trace.marker.colors */
    protected const FEATURE_COLORS_MARKER_COLORS = 'colors';

    /** @var string The plot type */
    public const TYPE = '';

    /** @var string When the values are aggregated */
    protected const AGGREGATION_MODE = self::AGGREGATION_OPTIONAL;

    /** @var null|string How the feature colors are given to Plotly */
    protected const FEATURE_COLORS = self::FEATURE_COLORS_NONE;

    /** @var bool The X field is mandatory */
    protected const X_MANDATORY = true;

    /** @var bool The Y field is mandatory */
    protected const Y_MANDATORY = true;

    /** @var bool The features are sorted by X */
    protected const SORT_BY_X = false;

    /** @var string Trace property containing the X values */
    protected const X_PROPERTY = 'x';

    /** @var string Trace property containing the Y values */
    protected const Y_PROPERTY = 'y';

    /** @var null|string Trace property containing the Z values */
    protected const Z_PROPERTY = null;

    protected Project $project;

    protected string $layerId;

    protected string $title;

    /** @var list<string> */
    protected array $xFields = array();

    /** @var list<string> */
    protected array $yFields = array();

    /** @var array<int, null|string> Z field of each trace */
    protected array $zFields = array();

    /** @var array<int, null|string> Color of each trace */
    protected array $colors = array();

    /** @var array<int, null|string> Field containing the feature color, for each trace */
    protected array $colorFields = array();

    /** @var null|string The aggregate function, null if the values are not aggregated */
    protected ?string $aggregation;

    /** @var bool Display the legend */
    protected bool $displayLegend;

    /** @var bool Stack the bars of the traces */
    protected bool $stacked;

    /** @var bool Display horizontal bars */
    protected bool $horizontal;

    /** @var string The dataviz theme: dark or light */
    protected string $theme;

    /** @var array The Plotly layout */
    protected array $layout;

    /** @var array The Plotly traces, the long lists of values are JsonColumn */
    protected array $data = array();

    /** @var null|array<string, string> Cache of the layer field aliases */
    private ?array $aliases = null;

    /**
     * Create the plot from its configuration.
     *
     * No data is fetched: call fetchData() or buildTraces() to build the traces.
     *
     * @param Project $project    the Lizmap project, the ACL must have been checked
     * @param array   $plotConfig the plot configuration, see Project::parseDatavizPlotConfig()
     */
    public function __construct(Project $project, array $plotConfig)
    {
        $this->project = $project;

        // Only the dark theme changes the plots (text color)
        $this->theme = $project->getOption('theme') === 'dark' ? 'dark' : 'light';
        $this->parsePlotConfig($plotConfig);

        // Build the layout for the plot type
        $this->layout = $this->getLayoutTemplate();

        // Optional layout additional options (legacy code)
        if (!empty($plotConfig['plot']['layout_config']) && is_array($plotConfig['plot']['layout_config'])) {
            $this->layout = array_merge($this->layout, $plotConfig['plot']['layout_config']);
        }
    }

    /**
     * Get the plot type.
     *
     * @return string the plot type, for example "bar" or "pie"
     */
    public function getType(): string
    {
        return static::TYPE;
    }

    /**
     * Get the plot title.
     *
     * @return string the plot title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the identifier of the layer containing the data.
     *
     * @return string the QGIS layer identifier
     */
    public function getLayerId(): string
    {
        return $this->layerId;
    }

    /**
     * Get the Plotly traces with all their values.
     *
     * All the values are loaded in memory: for a large number of features,
     * prefer writeJson() which uses less memory.
     *
     * @return array the Plotly traces
     */
    public function getData(): array
    {
        // Encode then decode the traces, to replace the columns by arrays
        return json_decode(JsonStreamWriter::encode($this->data), true);
    }

    /**
     * Get the Plotly traces, in which the long lists of values are JsonColumn.
     *
     * The traces can be written with JsonStreamWriter.
     *
     * @return array the Plotly traces
     */
    public function getTraces(): array
    {
        return $this->data;
    }

    /**
     * Write the plot as JSON: title, data (traces) and layout.
     *
     * The values are copied from their temporary streams.
     *
     * @param resource $output     the output stream
     * @param array    $properties additional properties of the JSON object
     */
    public function writeJson($output, array $properties = array()): void
    {
        // The JSON object of the plot, with the additional properties
        $plot = array_merge(
            array(
                'title' => $this->getTitle(),
                'data' => $this->getTraces(),
                'layout' => $this->getLayout(),
            ),
            $properties
        );
        JsonStreamWriter::write($plot, $output);
    }

    /**
     * Get the Plotly layout.
     *
     * @return array the Plotly layout
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * Get the aggregate function used for this plot.
     *
     * @return null|string the aggregate function, null if the values are not aggregated
     */
    public function getAggregation(): ?string
    {
        return $this->aggregation;
    }

    /**
     * Tell if the values are aggregated by distinct X values.
     *
     * @return bool true if the values are aggregated
     */
    public function isAggregated(): bool
    {
        return $this->aggregation !== null;
    }

    /**
     * Fetch the layer features with WFS and build the plot traces.
     *
     * @param string $expFilter a QGIS expression to filter the features
     *
     * @return bool false if the data cannot be fetched
     */
    public function fetchData(string $expFilter = ''): bool
    {
        // Do not request the data if the configuration is not valid
        if (!$this->hasMandatoryFields()) {
            return false;
        }

        // Request only the fields used by the plot. The features are
        // sorted by X for plots displaying the X values in this order.
        $reader = new WfsFeatureReader($this->project, $this->layerId);
        $features = $reader->read(
            $this->getPropertyNames(),
            $expFilter,
            static::SORT_BY_X ? $this->xFields : array(),
        );
        if ($features === null) {
            return false;
        }

        return $this->buildTraces($features);
    }

    /**
     * Build the plot traces from the given features.
     *
     * The features are read only once, so they can be given by a generator.
     *
     * @param iterable $features GeoJSON features (objects)
     *
     * @return bool false if the features are not valid
     */
    public function buildTraces(iterable $features): bool
    {
        if (!$this->hasMandatoryFields()) {
            return false;
        }

        // Aggregated values by X, or one value per feature
        try {
            $traces = $this->isAggregated()
                ? $this->buildAggregatedTraces($features)
                : $this->buildRawTraces($features);
        } catch (\JsonException $e) {
            // Invalid or truncated GeoJSON
            return false;
        }
        if ($traces === null) {
            return false;
        }
        $this->data = $traces;

        return true;
    }

    /**
     * Get the trace template of the plot type.
     *
     * @return array the Plotly trace properties, with empty data
     */
    abstract protected function getTraceTemplate(): array;

    /**
     * Customize the layout for the plot type.
     *
     * By default, the axis titles are the aliases of the X and Y fields,
     * when there is only one field for the axis.
     *
     * @param array $layout the default layout
     *
     * @return array the layout of the plot type
     */
    protected function customizeLayout(array $layout): array
    {
        // Use the field aliases as axis titles
        if (count($this->xFields) == 1) {
            $layout['xaxis']['title'] = $this->getFieldAlias($this->xFields[0]);
        }
        if (count($this->yFields) == 1) {
            $layout['yaxis']['title'] = $this->getFieldAlias($this->yFields[0]);
        }

        return $layout;
    }

    /**
     * Get the group of a feature for the aggregation.
     *
     * By default, the features are grouped by X value.
     *
     * @param object $properties the feature properties
     * @param mixed  $x          the feature X value, not null
     *
     * @return mixed the group value, a scalar or an array
     */
    protected function getGroup(object $properties, mixed $x): mixed
    {
        return $x;
    }

    /**
     * Set the aggregated values in the trace.
     *
     * By default, the groups are the categories and the aggregated values
     * are the values of the trace.
     *
     * @param array       $trace  the trace
     * @param list<mixed> $groups the groups
     * @param list<mixed> $values the aggregated value of each group
     * @param null|array  $colors the color of each group
     *
     * @return null|array the colors of the trace values
     */
    protected function setAggregatedValues(array &$trace, array $groups, array $values, ?array $colors): ?array
    {
        list($categoryProperty, $valueProperty) = $this->getAxisProperties($trace);
        $trace[$categoryProperty] = $groups;
        $trace[$valueProperty] = $values;

        return $colors;
    }

    /**
     * Get the field alias, or the field name if it has no alias.
     *
     * @param string $field the field name
     *
     * @return string the field alias or name
     */
    protected function getFieldAlias(string $field): string
    {
        // Get the aliases of the layer fields only once
        if ($this->aliases === null) {
            /** @var null|\qgisVectorLayer $layer */
            $layer = $this->project->getLayer($this->layerId);
            $this->aliases = $layer ? $layer->getAliasFields() : array();
        }

        return !empty($this->aliases[$field]) ? $this->aliases[$field] : $field;
    }

    /**
     * Read the plot configuration.
     *
     * @param array $plotConfig the plot configuration
     */
    private function parsePlotConfig(array $plotConfig): void
    {
        $this->layerId = (string) $plotConfig['layer_id'];
        $this->title = (string) ($plotConfig['title'] ?? '');
        $plot = $plotConfig['plot'];

        // Since Lizmap 3.4, a traces property contains all Y/color/Z
        // Colors, color fields and Z fields are indexed like the Y fields
        if (array_key_exists('traces', $plot)) {
            if (!empty($plot['x_field'])) {
                $this->xFields[] = $plot['x_field'];
            }
            foreach ($plot['traces'] as $trace) {
                $trace = (object) $trace;
                $this->yFields[] = $trace->y_field;
                $this->colors[] = self::emptyToNull($trace->color ?? null);
                $this->colorFields[] = self::emptyToNull($trace->colorfield ?? null);
                $this->zFields[] = self::emptyToNull($trace->z_field ?? null);
            }
        } else {
            // LEGACY CODE:  LIZMAP < 3.4
            // Comma separated fields, and at most two colors
            $this->xFields = self::splitFields($plot['x_field'] ?? '');
            $yFields = $plot['y_field'] ?? '';
            if (array_key_exists('y2_field', $plot)) {
                $yFields .= ','.$plot['y2_field'];
            }
            $this->yFields = self::splitFields($yFields);
            $this->zFields = self::splitFields($plot['z_field'] ?? '');
            $this->colors = array(
                self::emptyToNull($plot['color'] ?? null),
                self::emptyToNull($plot['color2'] ?? null),
            );
            $this->colorFields = array(
                self::emptyToNull($plot['colorfield'] ?? null),
                self::emptyToNull($plot['colorfield2'] ?? null),
            );
        }

        // Aggregation: sum by default when the property is missing (legacy)
        $this->aggregation = $this->resolveAggregation(
            array_key_exists('aggregation', $plot) ? $plot['aggregation'] : AggregateFunction::SUM
        );

        // Display options
        $this->displayLegend = (bool) ($plot['display_legend'] ?? true);
        $this->stacked = (bool) ($plot['stacked'] ?? false);
        $this->horizontal = (bool) ($plot['horizontal'] ?? false);
    }

    /**
     * Get the aggregate function to use for this plot type.
     *
     * @param mixed $aggregation the aggregation from the plot config
     *
     * @return null|string the aggregate function, null if values must not be aggregated
     */
    private function resolveAggregation(mixed $aggregation): ?string
    {
        // The plot type displays a distribution of the raw values
        if (static::AGGREGATION_MODE === self::AGGREGATION_NEVER) {
            return null;
        }

        // The plot type can not display the raw values: sum by default
        $function = AggregateFunction::normalize($aggregation);
        if ($function === null && static::AGGREGATION_MODE === self::AGGREGATION_ALWAYS) {
            $function = AggregateFunction::SUM;
        }

        return $function;
    }

    /**
     * Get the layout of the plot.
     *
     * @return array the Plotly layout
     */
    private function getLayoutTemplate(): array
    {
        // Default layout, shared by all the plot types
        $layout = array(
            'showlegend' => $this->displayLegend,
            'legend' => array(
                'orientation' => 'h',
                'x' => '-0.1',
                'y' => '1.15',
            ),
            'autosize' => true,
            'plot_bgcolor' => 'rgba(0,0,0,0)',
            'paper_bgcolor' => 'rgba(0,0,0,0)',
            'margin' => array(
                't' => 10,
                'b' => 30,
                'l' => 30,
                'r' => 30,
                'pad' => 1,
            ),
            'xaxis' => array(
                'tickfont' => array(
                    'size' => 10,
                ),
                'automargin' => true,
            ),
            'yaxis' => array(
                'tickfont' => array(
                    'size' => 10,
                ),
                'automargin' => true,
            ),
        );

        return $this->customizeLayout($layout);
    }

    /**
     * Check that the configuration contains the fields needed by the plot type.
     *
     * @return bool true if the mandatory fields are configured
     */
    private function hasMandatoryFields(): bool
    {
        if (static::X_MANDATORY && !$this->xFields) {
            return false;
        }
        if (static::Y_MANDATORY && !$this->yFields) {
            return false;
        }
        // The aggregation is made by distinct X values
        if ($this->isAggregated() && !$this->xFields) {
            return false;
        }

        return true;
    }

    /**
     * Get the fields to request: the fields used by the plot.
     *
     * @return list<string> the fields to request, without duplicates
     */
    private function getPropertyNames(): array
    {
        $propertyNames = array_merge($this->xFields, $this->yFields, $this->colorFields);
        if (static::Z_PROPERTY !== null) {
            $propertyNames = array_merge($propertyNames, $this->zFields);
        }

        return array_values(array_unique(array_filter($propertyNames)));
    }

    /**
     * Check that the given feature contains the fields used by the plot.
     *
     * Only the first feature is checked.
     *
     * @param mixed $feature the GeoJSON feature
     *
     * @return bool true if the feature has properties containing the X and Y fields
     */
    private function isValidFeature(mixed $feature): bool
    {
        // A feature with properties
        if (!is_object($feature)
            || !property_exists($feature, 'properties')
            || !is_object($feature->properties)
        ) {
            return false;
        }
        // containing the X and Y fields (null values are allowed)
        foreach (array_merge($this->xFields, $this->yFields) as $field) {
            if (!property_exists($feature->properties, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the fields used by each trace.
     *
     * The Z field is used only by the plot types having a Z property.
     *
     * @return list<array{y: string, z: null|string, color: null|string}> the fields of each trace
     */
    private function getTraceFields(): array
    {
        $fields = array();
        foreach ($this->yFields as $i => $yField) {
            $fields[] = array(
                'y' => $yField,
                'z' => static::Z_PROPERTY !== null ? ($this->zFields[$i] ?? null) : null,
                'color' => $this->colorFields[$i] ?? null,
            );
        }

        return $fields;
    }

    /**
     * Build the traces with one value per feature.
     *
     * The values are stored as JSON in temporary streams (JsonColumn),
     * so the memory used does not depend on the number of features.
     *
     * @param iterable $features the GeoJSON features
     *
     * @return null|array the traces, null if the features are not valid
     */
    private function buildRawTraces(iterable $features): ?array
    {
        $xField = count($this->xFields) == 1 ? $this->xFields[0] : null;
        $traceFields = $this->getTraceFields();

        // The X values are shared by all the traces
        $xValues = $xField !== null ? new JsonColumn() : null;

        // Create the traces and the columns of their values and colors
        $traces = array();
        $yValues = array();
        $colors = array();
        $defaultColors = array();
        $hasColors = array();
        foreach ($traceFields as $i => $fields) {
            $traces[$i] = $this->createTrace($i, $fields['y']);
            $yValues[$i] = new JsonColumn();
            // The colors are collected only if the plot type displays them
            $colors[$i] = null;
            if ($fields['color'] !== null && static::FEATURE_COLORS !== self::FEATURE_COLORS_NONE) {
                $colors[$i] = new JsonColumn();
            }
            // Use the trace color for the values without color
            $defaultColors[$i] = null;
            if (static::FEATURE_COLORS === self::FEATURE_COLORS_MARKER_COLOR) {
                $defaultColors[$i] = $traces[$i]['marker']['color'] ?? null;
            }
            $hasColors[$i] = false;
        }

        // Add the values of each feature
        $first = true;
        foreach ($features as $feature) {
            // Check the fields of the first feature only
            if ($first) {
                if (!$this->isValidFeature($feature)) {
                    return null;
                }
                $first = false;
            }
            $properties = $feature->properties;

            if ($xValues !== null) {
                $xValues->append($properties->{$xField} ?? null);
            }
            foreach ($traceFields as $i => $fields) {
                $yValues[$i]->append($properties->{$fields['y']} ?? null);
                // An empty color is replaced by the trace color
                if ($colors[$i] !== null) {
                    $color = $properties->{$fields['color']} ?? null;
                    if ($color === null || $color === '') {
                        $color = $defaultColors[$i];
                    } else {
                        $hasColors[$i] = true;
                    }
                    $colors[$i]->append($color);
                }
            }
        }

        // No feature: no trace
        if ($first) {
            return array();
        }

        // Set the columns in the traces
        foreach ($traces as $i => $trace) {
            list($categoryProperty, $valueProperty) = $this->getAxisProperties($trace);
            if ($xValues !== null) {
                // The same column can be used by several traces
                $trace[$categoryProperty] = $xValues;
            }
            $trace[$valueProperty] = $yValues[$i];

            // The colors are set only if at least one feature has a color
            if ($hasColors[$i]) {
                $this->setFeatureColors($trace, $colors[$i]);
            }
            $traces[$i] = $this->finalizeTrace($trace);
        }

        return $traces;
    }

    /**
     * Build the traces with one aggregated value per group (distinct X value).
     *
     * The values are aggregated in a single pass: only the state needed by
     * the aggregate function is kept for each group.
     *
     * @param iterable $features the GeoJSON features
     *
     * @return null|array the traces, null if the features are not valid
     */
    private function buildAggregatedTraces(iterable $features): ?array
    {
        $xField = $this->xFields[0];
        $traceFields = $this->getTraceFields();

        // The groups are shared by all the traces, so the values are aligned
        $groups = new GroupIndex();
        $aggregators = array();
        foreach ($traceFields as $i => $fields) {
            $aggregators[$i] = new TraceAggregator($this->aggregation);
        }

        // Aggregate the values of each feature
        $first = true;
        foreach ($features as $feature) {
            // Check the fields of the first feature only
            if ($first) {
                if (!$this->isValidFeature($feature)) {
                    return null;
                }
                $first = false;
            }
            $properties = $feature->properties;

            // Features without X value are ignored
            $x = $properties->{$xField} ?? null;
            if ($x === null) {
                continue;
            }

            // Add the feature values to its group, for each trace
            $group = $groups->positionOf($this->getGroup($properties, $x));

            foreach ($traceFields as $i => $fields) {
                $aggregators[$i]->add(
                    $group,
                    $properties->{$fields['y']} ?? null,
                    $fields['color'] !== null ? ($properties->{$fields['color']} ?? null) : null,
                );
            }
        }

        // No feature: no trace
        if ($first) {
            return array();
        }

        $groupCount = $groups->count();
        $groupValues = $groups->values();

        // Create the traces with the aggregated values of the groups
        $traces = array();
        foreach ($traceFields as $i => $fields) {
            $trace = $this->createTrace($i, $fields['y']);
            $colors = $this->setAggregatedValues(
                $trace,
                $groupValues,
                $aggregators[$i]->results($groupCount),
                $aggregators[$i]->colors($groupCount),
            );
            // Free the aggregator memory as soon as possible
            $aggregators[$i] = null;

            $this->applyColors($trace, $colors);
            $traces[] = $this->finalizeTrace($trace);
        }

        return $traces;
    }

    /**
     * Create a trace from the template of the plot type.
     *
     * @param int    $index  the trace index in the configuration
     * @param string $yField the trace Y field
     *
     * @return array the trace, with its name and color, without data
     */
    private function createTrace(int $index, string $yField): array
    {
        $trace = $this->getTraceTemplate();

        // Set trace name. Use QGIS field alias if present
        $trace['name'] = $this->getFieldAlias($yField);

        // Set the trace color, in the property used by the plot type
        $color = $this->colors[$index] ?? null;
        if ($color !== null) {
            if (array_key_exists('marker', $trace)) {
                $trace['marker']['color'] = $color;
            }
            if (array_key_exists('fillcolor', $trace)) {
                $trace['fillcolor'] = $color;
            }
        }

        return $trace;
    }

    /**
     * Get the properties of the trace containing the categories (X field)
     * and the values (Y field). They are reverted for horizontal plots.
     *
     * @param array $trace the trace
     *
     * @return array{0: string, 1: string} the category and the value properties
     */
    private function getAxisProperties(array $trace): array
    {
        if (($trace['orientation'] ?? null) == 'h') {
            return array(static::Y_PROPERTY, static::X_PROPERTY);
        }

        return array(static::X_PROPERTY, static::Y_PROPERTY);
    }

    /**
     * Set the aggregated colors in the trace.
     *
     * @param array      $trace  the trace
     * @param null|array $colors the color of each value, null if none
     */
    private function applyColors(array &$trace, ?array $colors): void
    {
        // No color: keep the trace color
        if ($colors === null) {
            return;
        }
        if (static::FEATURE_COLORS === self::FEATURE_COLORS_MARKER_COLOR) {
            // Use the trace color for the values without color
            $traceColor = $trace['marker']['color'] ?? null;
            foreach ($colors as $i => $color) {
                if ($color === null) {
                    $colors[$i] = $traceColor;
                }
            }
        }
        $this->setFeatureColors($trace, $colors);
    }

    /**
     * Set the feature colors in the trace property used by the plot type.
     *
     * @param array            $trace  the trace
     * @param array|JsonColumn $colors the color of each value
     */
    private function setFeatureColors(array &$trace, array|JsonColumn $colors): void
    {
        // Pie like plots use marker.colors, the others marker.color
        if (static::FEATURE_COLORS === self::FEATURE_COLORS_MARKER_COLORS) {
            $trace['marker']['colors'] = $colors;
            unset($trace['marker']['color']);
        } elseif (static::FEATURE_COLORS === self::FEATURE_COLORS_MARKER_COLOR) {
            $trace['marker']['color'] = $colors;
        }
    }

    /**
     * Replace empty data arrays or columns by null.
     *
     * @param array $trace the trace
     *
     * @return array the trace
     */
    private function finalizeTrace(array $trace): array
    {
        foreach (array(static::X_PROPERTY, static::Y_PROPERTY, static::Z_PROPERTY) as $property) {
            $values = $property !== null ? ($trace[$property] ?? null) : null;
            if ((is_array($values) || $values instanceof JsonColumn) && count($values) == 0) {
                $trace[$property] = null;
            }
        }

        return $trace;
    }

    /**
     * Replace an empty string by null.
     *
     * @param mixed $value the value
     *
     * @return mixed the value, or null if it is empty
     */
    private static function emptyToNull(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    /**
     * Split a comma separated list of fields.
     *
     * @param string $fields a comma separated list of fields
     *
     * @return list<string> the fields, without empty ones
     */
    private static function splitFields(string $fields): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $fields)), 'strlen'));
    }
}
