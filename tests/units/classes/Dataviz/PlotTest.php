<?php

use Lizmap\Project\Project;
use LizmapDataviz\Plot\AbstractPlot;
use LizmapDataviz\Plot\BoxPlot;
use LizmapDataviz\Plot\PiePlot;
use LizmapDataviz\Plot\PlotFactory;
use LizmapDataviz\Plot\SunburstPlot;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the dataviz plots: creation from the configuration, traces built
 * from GeoJSON features (raw or aggregated values), layout and JSON output.
 *
 * The features are given directly to buildTraces(), without WFS request.
 *
 * @internal
 *
 * @coversNothing
 */
class PlotTest extends TestCase
{
    /**
     * Get a mock of the Lizmap project.
     *
     * The layer is not found, so the field names are used instead of aliases.
     *
     * @param string $theme the dataviz theme option of the project
     *
     * @return Project the project mock
     */
    private function getProject(string $theme = 'light'): Project
    {
        $project = $this->createMock(Project::class);
        $project->method('getOption')->willReturnMap(array(array('theme', $theme)));
        $project->method('getLayer')->willReturn(null);

        return $project;
    }

    /**
     * Get a plot configuration with one trace.
     *
     * The trace uses all the fields of the test features: X "category",
     * Y "value", color "color" and Z "parent" (used by sunburst only).
     *
     * @param string $type        the plot type
     * @param string $aggregation the aggregation
     * @param array  $plot        plot properties overriding the default ones
     *
     * @return array the plot configuration
     */
    private static function getPlotConfig(string $type, string $aggregation, array $plot = array()): array
    {
        return array(
            'layer_id' => 'layer',
            'title' => 'Title',
            'plot' => array_merge(array(
                'type' => $type,
                'x_field' => 'category',
                'aggregation' => $aggregation,
                'traces' => array(
                    (object) array(
                        'y_field' => 'value',
                        'color' => 'green',
                        'colorfield' => 'color',
                        'z_field' => 'parent',
                    ),
                ),
                'display_legend' => true,
                'stacked' => false,
                'horizontal' => false,
            ), $plot),
        );
    }

    /**
     * Get the test features, as decoded from GeoJSON.
     *
     * Category A has two features (values 1 and 3) with different parents,
     * B has a numeric string value and an empty color, "1.5" is a numeric
     * string category, and the last feature has no category.
     *
     * @return array the features
     */
    private static function getFeatures(): array
    {
        $features = array();
        foreach (array(
            // category, value, color, parent
            array('A', 1, 'red', 'P1'),
            array('B', '2', '', 'P1'),
            array('A', 3, 'blue', 'P2'),
            array('1.5', 4, null, null),
            array(null, 9, null, null),
        ) as list($category, $value, $color, $parent)) {
            $features[] = (object) array(
                'type' => 'Feature',
                'properties' => (object) array(
                    'category' => $category,
                    'value' => $value,
                    'color' => $color,
                    'parent' => $parent,
                ),
            );
        }

        return $features;
    }

    /**
     * Create a plot and build its traces, checking that it succeeds.
     *
     * @param array         $plotConfig the plot configuration
     * @param null|iterable $features   the features, the test features by default
     *
     * @return AbstractPlot the plot with its traces
     */
    private function buildPlot(array $plotConfig, ?iterable $features = null): AbstractPlot
    {
        $plot = PlotFactory::create($this->getProject(), $plotConfig);
        $this->assertNotNull($plot);
        $this->assertTrue($plot->buildTraces($features ?? self::getFeatures()));

        return $plot;
    }

    /**
     * Test that the factory creates the plot class of the configured type,
     * and nothing for an unknown type.
     */
    public function testFactory(): void
    {
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('pie', 'sum'));
        $this->assertInstanceOf(PiePlot::class, $plot);
        $this->assertEquals('Title', $plot->getTitle());

        $this->assertNull(PlotFactory::create($this->getProject(), self::getPlotConfig('unknown', 'sum')));
    }

    /**
     * Test that pie values are always aggregated, with a sum when
     * no aggregation is configured.
     */
    public function testPieAlwaysAggregated(): void
    {
        foreach (array('sum', 'no', '') as $aggregation) {
            $plot = $this->buildPlot(self::getPlotConfig('pie', $aggregation));
            $this->assertTrue($plot->isAggregated());
            $trace = $plot->getData()[0];

            // One slice per category, the feature without category is ignored
            $this->assertSame(array('A', 'B', '1.5'), $trace['labels']);
            $this->assertSame(array(4, 2, 4), $trace['values']);

            // Pie colors are in marker.colors: the first color of each category
            $this->assertSame(array('red', null, null), $trace['marker']['colors']);
            $this->assertArrayNotHasKey('color', $trace['marker']);
        }
    }

    /**
     * Test the median aggregation in a plot.
     */
    public function testPieMedian(): void
    {
        // Median of A (1, 3) is 2, B and 1.5 have a single value
        $plot = $this->buildPlot(self::getPlotConfig('pie', 'median'));
        $this->assertSame(array(2, 2, 4), $plot->getData()[0]['values']);
    }

    /**
     * Test a bar plot without aggregation: one value per feature,
     * and no Plotly aggregate transform anymore.
     */
    public function testBarRaw(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('bar', 'no'));
        $this->assertFalse($plot->isAggregated());
        $trace = $plot->getData()[0];

        // The values of all the features, in the same order
        $this->assertSame(array('A', 'B', 'A', '1.5', null), $trace['x']);
        $this->assertSame(array(1, '2', 3, 4, 9), $trace['y']);

        // The trace color is used for features without color
        $this->assertSame(array('red', 'green', 'blue', 'green', 'green'), $trace['marker']['color']);
        $this->assertArrayNotHasKey('transforms', $trace);
    }

    /**
     * Test a horizontal bar plot: the categories are on the Y axis
     * and the aggregated values on the X axis.
     */
    public function testHorizontalBarAggregated(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('bar', 'count', array('horizontal' => true)));
        $trace = $plot->getData()[0];
        $this->assertEquals('h', $trace['orientation']);
        $this->assertSame(array('A', 'B', '1.5'), $trace['y']);
        $this->assertSame(array(2, 1, 1), $trace['x']);
    }

    /**
     * Test that box plots are never aggregated, as they display
     * the distribution of the values.
     */
    public function testBoxNeverAggregated(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('box', 'count'));
        $this->assertInstanceOf(BoxPlot::class, $plot);
        $this->assertFalse($plot->isAggregated());
        $trace = $plot->getData()[0];
        $this->assertCount(5, $trace['y']);

        // The trace color is the box fill color
        $this->assertEquals('green', $trace['fillcolor']);
    }

    /**
     * Test the sunburst hierarchy: the root, the parents and the groups
     * aggregated by (parent, label).
     */
    public function testSunburst(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('sunburst', 'sum'));
        $trace = $plot->getData()[0];
        $root = SunburstPlot::ROOT;

        // Nodes: root, parents P1 and P2, then the groups. The label A is
        // used twice, under P1 and P2. "1.5" has no parent: it is under the root.
        $this->assertSame(array($root, 'P1', 'P2', 'A', 'B', 'A', '1.5'), $trace['labels']);

        // The root value is the sum of all values, a parent value is the
        // sum of its children values
        $this->assertSame(array(10, 3, 3, 1, 2, 3, 4), $trace['values']);

        // The parents are given by node ids, as the labels are not unique
        $this->assertSame(
            array('', $root, $root, $root.'/0', $root.'/0', $root.'/1', $root),
            $trace['parents']
        );
        $this->assertCount(7, array_unique($trace['ids']));

        // White root, a parent has the color of its first child
        $this->assertSame(array('white', 'red', 'blue', 'red', null, 'blue', null), $trace['marker']['colors']);
    }

    /**
     * Test that there is no trace when there is no feature.
     */
    public function testNoFeature(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('pie', 'sum'), array());
        $this->assertSame(array(), $plot->getData());
    }

    /**
     * Test that features without the configured fields are rejected.
     */
    public function testInvalidFeature(): void
    {
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('pie', 'sum'));
        $feature = (object) array('properties' => (object) array('other' => 1));
        $this->assertFalse($plot->buildTraces(array($feature)));
    }

    /**
     * Test that a configuration without a mandatory field is rejected.
     */
    public function testMissingMandatoryField(): void
    {
        // Bar plots need an X field
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('bar', 'sum', array('x_field' => '')));
        $this->assertFalse($plot->buildTraces(self::getFeatures()));
    }

    /**
     * Test the layout customized by the plot types.
     */
    public function testLayout(): void
    {
        // Pie without legend: small bottom margin and no axis title
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('pie', 'sum', array('display_legend' => false)));
        $layout = $plot->getLayout();
        $this->assertFalse($layout['showlegend']);
        $this->assertEquals(10, $layout['margin']['b']);
        $this->assertArrayNotHasKey('title', $layout['xaxis']);

        // Scatter: the X axis title is the X field alias (here its name)
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('scatter', 'sum'));
        $this->assertEquals('category', $plot->getLayout()['xaxis']['title']);
    }

    /**
     * Test that the dark theme of the project changes the text color.
     */
    public function testDarkTheme(): void
    {
        $plot = PlotFactory::create($this->getProject('dark'), self::getPlotConfig('pie', 'sum'));
        $plot->buildTraces(self::getFeatures());
        $this->assertEquals(array('color' => 'white'), $plot->getData()[0]['outsidetextfont']);
    }

    /**
     * Test that the streamed JSON contains the same plot as getData(),
     * for raw values (stored in columns) and aggregated values.
     */
    public function testWriteJson(): void
    {
        foreach (array(array('bar', 'no'), array('pie', 'sum'), array('sunburst', 'count')) as list($type, $aggregation)) {
            // Write the plot JSON with an additional property
            $plot = $this->buildPlot(self::getPlotConfig($type, $aggregation));
            $output = fopen('php://memory', 'r+');
            $plot->writeJson($output, array('plotly' => array('script' => 'plotly.js')));
            rewind($output);
            $json = json_decode(stream_get_contents($output), true);

            // Same title, traces and layout, plus the additional property
            $this->assertEquals('Title', $json['title']);
            $this->assertSame($plot->getData(), $json['data']);
            $this->assertEquals($plot->getLayout(), $json['layout']);
            $this->assertEquals(array('script' => 'plotly.js'), $json['plotly']);
        }
    }
}
