<?php

use LizmapDataviz\Aggregation\AggregateFunction;
use LizmapDataviz\Aggregation\GroupIndex;
use LizmapDataviz\Aggregation\TraceAggregator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class TraceAggregatorTest extends TestCase
{
    /**
     * Features as [x, y, color].
     */
    private const FEATURES = array(
        array('A', 1, 'red'),
        array('B', '2', ''),
        array('A', 3, 'blue'),
        array('A', 8, null),
        array('B', 'not a number', null),
        array('C', null, null),
    );

    public static function aggregationProvider(): array
    {
        return array(
            'sum' => array(AggregateFunction::SUM, array(12, 2, 0)),
            'count' => array(AggregateFunction::COUNT, array(3, 2, 1)),
            'avg' => array(AggregateFunction::AVG, array(4, 2, null)),
            'min' => array(AggregateFunction::MIN, array(1, 2, null)),
            'max' => array(AggregateFunction::MAX, array(8, 2, null)),
            'first' => array(AggregateFunction::FIRST, array(1, '2', null)),
            'last' => array(AggregateFunction::LAST, array(8, 'not a number', null)),
            'median' => array(AggregateFunction::MEDIAN, array(3, 2, null)),
        );
    }

    #[DataProvider('aggregationProvider')]
    public function testAggregation(string $function, array $expected): void
    {
        $groups = new GroupIndex();
        $aggregator = new TraceAggregator($function);
        foreach (self::FEATURES as list($x, $y, $color)) {
            $aggregator->add($groups->positionOf($x), $y, $color);
        }

        $this->assertSame(array('A', 'B', 'C'), $groups->values());
        $this->assertSame($expected, $aggregator->results($groups->count()));
        $this->assertSame(array('red', null, null), $aggregator->colors($groups->count()));
    }

    public function testStddev(): void
    {
        $aggregator = new TraceAggregator(AggregateFunction::STDDEV);
        foreach (array(2, 4, 4, 4, 5, 5, 7, 9) as $value) {
            $aggregator->add(0, $value);
        }
        $aggregator->add(1, 'text');

        // Population standard deviation
        $this->assertSame(array(2.0, null), $aggregator->results(2));
    }

    public function testMedianEvenCount(): void
    {
        $aggregator = new TraceAggregator(AggregateFunction::MEDIAN);
        foreach (array(10, 1, 4, 3) as $value) {
            $aggregator->add(0, $value);
        }

        $this->assertSame(array(3.5), $aggregator->results(1));
    }

    public function testNoColors(): void
    {
        $aggregator = new TraceAggregator(AggregateFunction::SUM);
        $aggregator->add(0, 1, '');
        $aggregator->add(1, 1);

        $this->assertNull($aggregator->colors(2));
    }

    public function testUnknownFunction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TraceAggregator('mode');
    }

    public function testGroupIndexKeepsValueTypes(): void
    {
        $groups = new GroupIndex();
        $this->assertSame(0, $groups->positionOf(1.5));
        $this->assertSame(1, $groups->positionOf(1.7));
        $this->assertSame(2, $groups->positionOf('1'));
        $this->assertSame(3, $groups->positionOf(1));
        $this->assertSame(4, $groups->positionOf(true));
        $this->assertSame(5, $groups->positionOf(array('P1', 'A')));
        $this->assertSame(0, $groups->positionOf(1.5));
        $this->assertSame(2, $groups->positionOf('1'));
        $this->assertSame(5, $groups->positionOf(array('P1', 'A')));

        $this->assertSame(array(1.5, 1.7, '1', 1, true, array('P1', 'A')), $groups->values());
    }

    public static function normalizeProvider(): array
    {
        return array(
            array('sum', 'sum'),
            array(' Count ', 'count'),
            array('mean', 'avg'),
            array('', null),
            array('no', null),
            array('none', null),
            array(null, null),
            array('mode', null),
        );
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalize(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, AggregateFunction::normalize($value));
    }
}
