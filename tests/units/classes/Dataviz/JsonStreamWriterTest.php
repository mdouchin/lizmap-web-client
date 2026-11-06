<?php

use LizmapDataviz\Json\JsonColumn;
use LizmapDataviz\Json\JsonStreamWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the JSON columns and of the streamed JSON writer.
 *
 * The streamed JSON must be exactly the same as the one written
 * by json_encode with the values in arrays.
 *
 * @internal
 *
 * @coversNothing
 */
class JsonStreamWriterTest extends TestCase
{
    /**
     * Get test values of all the JSON types.
     *
     * The strings contain characters escaped in JSON, and a string looking
     * like the placeholder used by the writer to locate the columns.
     *
     * @param int $count the number of values
     *
     * @return array the values
     */
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

    /**
     * Create a column containing the given values.
     *
     * @param array $values the values
     *
     * @return JsonColumn the column
     */
    private static function column(array $values): JsonColumn
    {
        $column = new JsonColumn();
        foreach ($values as $value) {
            $column->append($value);
        }

        return $column;
    }

    /**
     * Test that a large column, written in a temporary file,
     * returns the same values.
     */
    public function testColumn(): void
    {
        // About 1.5 MB of JSON: more than the memory kept by the column
        $values = self::values(100000);
        $column = self::column($values);

        // The expected values are the ones after a JSON round trip
        $this->assertCount(100000, $column);
        $this->assertSame(json_decode(json_encode($values), true), $column->jsonSerialize());
    }

    /**
     * Test that an empty column is written as an empty JSON array.
     */
    public function testEmptyColumn(): void
    {
        $column = new JsonColumn();

        $this->assertCount(0, $column);
        $this->assertSame('{"values":[]}', JsonStreamWriter::encode(array('values' => $column)));
    }

    /**
     * Test that the streamed JSON is the same as json_encode with arrays.
     */
    public function testWrite(): void
    {
        $x = self::values(20000);
        $y = range(1, 20000);

        // Plot data with columns, the X column is used by the two traces
        $xColumn = self::column($x);
        $data = array(
            'title' => 'Title "quoted"',
            'data' => array(
                array('type' => 'bar', 'x' => $xColumn, 'y' => self::column($y), 'marker' => array('color' => 'red')),
                array('type' => 'bar', 'x' => $xColumn, 'y' => array(1, 2), 'text' => array()),
            ),
            'layout' => array('showlegend' => true),
        );

        // The same data with arrays instead of the columns
        $expected = array(
            'title' => 'Title "quoted"',
            'data' => array(
                array('type' => 'bar', 'x' => $x, 'y' => $y, 'marker' => array('color' => 'red')),
                array('type' => 'bar', 'x' => $x, 'y' => array(1, 2), 'text' => array()),
            ),
            'layout' => array('showlegend' => true),
        );

        // Write the data in a stream
        $output = fopen('php://memory', 'r+');
        JsonStreamWriter::write($data, $output);
        rewind($output);
        $json = stream_get_contents($output);

        // The JSON strings are compared, not only the decoded values
        $this->assertSame(json_encode($expected), $json);
    }

    /**
     * Test that data without column is written as with json_encode.
     */
    public function testWriteWithoutColumn(): void
    {
        $data = array('a' => array(1, 2.5, 'b'), 'c' => null);

        $this->assertSame(json_encode($data), JsonStreamWriter::encode($data));
    }
}
