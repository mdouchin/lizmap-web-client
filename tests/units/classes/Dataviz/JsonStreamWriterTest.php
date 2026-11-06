<?php

use LizmapDataviz\Json\JsonColumn;
use LizmapDataviz\Json\JsonStreamWriter;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class JsonStreamWriterTest extends TestCase
{
    private static function values(int $count): array
    {
        $values = array();
        for ($i = 0; $i < $count; ++$i) {
            $values[] = match ($i % 6) {
                0 => $i,
                1 => $i / 7,
                2 => 'Value "'.$i.'" é/€\n',
                3 => null,
                4 => true,
                5 => '@column:00:0@',
            };
        }

        return $values;
    }

    private static function column(array $values): JsonColumn
    {
        $column = new JsonColumn();
        foreach ($values as $value) {
            $column->append($value);
        }

        return $column;
    }

    public function testColumn(): void
    {
        // Large enough to be written in a temporary file
        $values = self::values(100000);
        $column = self::column($values);

        $this->assertCount(100000, $column);
        $this->assertSame(json_decode(json_encode($values), true), $column->jsonSerialize());
    }

    public function testEmptyColumn(): void
    {
        $column = new JsonColumn();

        $this->assertCount(0, $column);
        $this->assertSame('{"values":[]}', JsonStreamWriter::encode(array('values' => $column)));
    }

    public function testWrite(): void
    {
        $x = self::values(20000);
        $y = range(1, 20000);
        $xColumn = self::column($x);
        $data = array(
            'title' => 'Title "quoted"',
            'data' => array(
                // The same column used twice
                array('type' => 'bar', 'x' => $xColumn, 'y' => self::column($y), 'marker' => array('color' => 'red')),
                array('type' => 'bar', 'x' => $xColumn, 'y' => array(1, 2), 'text' => array()),
            ),
            'layout' => array('showlegend' => true),
        );
        $expected = array(
            'title' => 'Title "quoted"',
            'data' => array(
                array('type' => 'bar', 'x' => $x, 'y' => $y, 'marker' => array('color' => 'red')),
                array('type' => 'bar', 'x' => $x, 'y' => array(1, 2), 'text' => array()),
            ),
            'layout' => array('showlegend' => true),
        );

        $output = fopen('php://memory', 'r+');
        JsonStreamWriter::write($data, $output);
        rewind($output);
        $json = stream_get_contents($output);

        $this->assertSame(json_encode($expected), $json);
    }

    public function testWriteWithoutColumn(): void
    {
        $data = array('a' => array(1, 2.5, 'b'), 'c' => null);

        $this->assertSame(json_encode($data), JsonStreamWriter::encode($data));
    }
}
