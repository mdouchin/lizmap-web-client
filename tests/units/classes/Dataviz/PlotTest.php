<?php

use Lizmap\Project\Project;
use LizmapDataviz\Plot\AbstractPlot;
use LizmapDataviz\Plot\BoxPlot;
use LizmapDataviz\Plot\PiePlot;
use LizmapDataviz\Plot\PlotFactory;
use LizmapDataviz\Plot\SunburstPlot;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class PlotTest extends TestCase
{
    private function getProject(string $theme = 'light'): Project
    {
        $project = $this->createMock(Project::class);
        $project->method('getOption')->willReturnMap(array(array('theme', $theme)));
        $project->method('getLayer')->willReturn(null);

        return $project;
    }

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

    private static function getFeatures(): array
    {
        $features = array();
        foreach (array(
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

    private function buildPlot(array $plotConfig, ?iterable $features = null): AbstractPlot
    {
        $plot = PlotFactory::create($this->getProject(), $plotConfig);
        $this->assertNotNull($plot);
        $this->assertTrue($plot->buildTraces($features ?? self::getFeatures()));

        return $plot;
    }

    public function testFactory(): void
    {
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('pie', 'sum'));
        $this->assertInstanceOf(PiePlot::class, $plot);
        $this->assertEquals('Title', $plot->getTitle());

        $this->assertNull(PlotFactory::create($this->getProject(), self::getPlotConfig('unknown', 'sum')));
    }

    public function testPieAlwaysAggregated(): void
    {
        foreach (array('sum', 'no', '') as $aggregation) {
            $plot = $this->buildPlot(self::getPlotConfig('pie', $aggregation));
            $this->assertTrue($plot->isAggregated());
            $trace = $plot->getData()[0];
            $this->assertSame(array('A', 'B', '1.5'), $trace['labels']);
            $this->assertSame(array(4, 2, 4), $trace['values']);
            $this->assertSame(array('red', null, null), $trace['marker']['colors']);
            $this->assertArrayNotHasKey('color', $trace['marker']);
        }
    }

    public function testPieMedian(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('pie', 'median'));
        $this->assertSame(array(2, 2, 4), $plot->getData()[0]['values']);
    }

    public function testBarRaw(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('bar', 'no'));
        $this->assertFalse($plot->isAggregated());
        $trace = $plot->getData()[0];
        $this->assertSame(array('A', 'B', 'A', '1.5', null), $trace['x']);
        $this->assertSame(array(1, '2', 3, 4, 9), $trace['y']);
        // The trace color is used for features without color
        $this->assertSame(array('red', 'green', 'blue', 'green', 'green'), $trace['marker']['color']);
        $this->assertArrayNotHasKey('transforms', $trace);
    }

    public function testHorizontalBarAggregated(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('bar', 'count', array('horizontal' => true)));
        $trace = $plot->getData()[0];
        $this->assertEquals('h', $trace['orientation']);
        $this->assertSame(array('A', 'B', '1.5'), $trace['y']);
        $this->assertSame(array(2, 1, 1), $trace['x']);
    }

    public function testBoxNeverAggregated(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('box', 'count'));
        $this->assertInstanceOf(BoxPlot::class, $plot);
        $this->assertFalse($plot->isAggregated());
        $trace = $plot->getData()[0];
        $this->assertCount(5, $trace['y']);
        $this->assertEquals('green', $trace['fillcolor']);
    }

    public function testSunburst(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('sunburst', 'sum'));
        $trace = $plot->getData()[0];
        $root = SunburstPlot::ROOT;
        $this->assertSame(array($root, 'P1', 'P2', 'A', 'B', 'A', '1.5'), $trace['labels']);
        $this->assertSame(array(10, 3, 3, 1, 2, 3, 4), $trace['values']);
        $this->assertSame(
            array('', $root, $root, $root.'/0', $root.'/0', $root.'/1', $root),
            $trace['parents']
        );
        $this->assertCount(7, array_unique($trace['ids']));
        $this->assertSame(array('white', 'red', 'blue', 'red', null, 'blue', null), $trace['marker']['colors']);
    }

    public function testNoFeature(): void
    {
        $plot = $this->buildPlot(self::getPlotConfig('pie', 'sum'), array());
        $this->assertSame(array(), $plot->getData());
    }

    public function testInvalidFeature(): void
    {
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('pie', 'sum'));
        $feature = (object) array('properties' => (object) array('other' => 1));
        $this->assertFalse($plot->buildTraces(array($feature)));
    }

    public function testMissingMandatoryField(): void
    {
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('bar', 'sum', array('x_field' => '')));
        $this->assertFalse($plot->buildTraces(self::getFeatures()));
    }

    public function testLayout(): void
    {
        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('pie', 'sum', array('display_legend' => false)));
        $layout = $plot->getLayout();
        $this->assertFalse($layout['showlegend']);
        $this->assertEquals(10, $layout['margin']['b']);
        $this->assertArrayNotHasKey('title', $layout['xaxis']);

        $plot = PlotFactory::create($this->getProject(), self::getPlotConfig('scatter', 'sum'));
        $this->assertEquals('category', $plot->getLayout()['xaxis']['title']);
    }

    public function testDarkTheme(): void
    {
        $plot = PlotFactory::create($this->getProject('dark'), self::getPlotConfig('pie', 'sum'));
        $plot->buildTraces(self::getFeatures());
        $this->assertEquals(array('color' => 'white'), $plot->getData()[0]['outsidetextfont']);
    }

    public function testWriteJson(): void
    {
        foreach (array(array('bar', 'no'), array('pie', 'sum'), array('sunburst', 'count')) as list($type, $aggregation)) {
            $plot = $this->buildPlot(self::getPlotConfig($type, $aggregation));
            $output = fopen('php://memory', 'r+');
            $plot->writeJson($output, array('plotly' => array('script' => 'plotly.js')));
            rewind($output);
            $json = json_decode(stream_get_contents($output), true);

            $this->assertEquals('Title', $json['title']);
            $this->assertSame($plot->getData(), $json['data']);
            $this->assertEquals($plot->getLayout(), $json['layout']);
            $this->assertEquals(array('script' => 'plotly.js'), $json['plotly']);
        }
    }
}
