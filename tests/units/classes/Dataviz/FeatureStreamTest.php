<?php

use LizmapDataviz\GeoJson\FeatureStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class FeatureStreamTest extends TestCase
{
    /**
     * @return resource
     */
    private static function stream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    private static function features(int $count): array
    {
        $features = array();
        for ($i = 0; $i < $count; ++$i) {
            $features[] = array(
                'type' => 'Feature',
                'id' => 'layer.'.$i,
                'geometry' => null,
                'properties' => array(
                    'name' => 'Feature '.$i,
                    // Separators between features inside strings and nested objects
                    'text' => str_repeat('x', $i % 13).'},{"a":1}, {'."\n".'"]}',
                    'json' => array(array('a' => 1), array('b' => array('c' => '},{'))),
                    'value' => $i * 1.5,
                ),
            );
        }

        return $features;
    }

    /**
     * GeoJSON written by QGIS Server: one feature per line.
     */
    private static function qgisServerGeoJson(array $features): string
    {
        $lines = array();
        foreach ($features as $feature) {
            $lines[] = json_encode($feature);
        }

        return "{\"type\": \"FeatureCollection\",\n \"bbox\": [ 3.8, 43.6, 3.9, 43.7],\n \"features\": [\n  "
            .implode("\n ,", $lines)
            ."\n ]\n}\n";
    }

    /**
     * GeoJSON written by the PostgreSQL direct query (row_to_json with line feeds).
     */
    private static function postgresqlGeoJson(array $features): string
    {
        $rows = array();
        foreach ($features as $feature) {
            $rows[] = '{"type":"Feature",'."\n".' "id":'.json_encode($feature['id']).",\n"
                .' "geometry":null,'."\n".' "properties":'.json_encode($feature['properties']).'}';
        }

        return '{"type": "FeatureCollection", "features": ['.implode(',', $rows).']}';
    }

    public static function geoJsonProvider(): array
    {
        $features = self::features(500);
        $pretty = json_encode(array('type' => 'FeatureCollection', 'features' => $features), JSON_PRETTY_PRINT);

        return array(
            'QGIS Server, small chunks' => array(self::qgisServerGeoJson($features), 100, 500),
            'QGIS Server, default chunks' => array(self::qgisServerGeoJson($features), 32768, 500),
            'PostgreSQL, small chunks' => array(self::postgresqlGeoJson($features), 100, 500),
            'PostgreSQL, default chunks' => array(self::postgresqlGeoJson($features), 32768, 500),
            'Pretty printed' => array($pretty, 256, 500),
            'Members after features' => array(
                json_encode(array('type' => 'FeatureCollection', 'features' => $features, 'numberMatched' => 500)),
                1024,
                500,
            ),
            'No feature' => array('{"type": "FeatureCollection", "features": []}', 32768, 0),
            'No features member' => array('{"type": "FeatureCollection"}', 32768, 0),
        );
    }

    #[DataProvider('geoJsonProvider')]
    public function testRead(string $geoJson, int $chunkSize, int $count): void
    {
        $expected = json_decode($geoJson)->features ?? array();

        $features = iterator_to_array(FeatureStream::read(self::stream($geoJson), $chunkSize), false);

        $this->assertCount($count, $features);
        $this->assertEquals($expected, $features);
    }

    public function testTruncatedGeoJson(): void
    {
        $geoJson = self::qgisServerGeoJson(self::features(100));
        $truncated = substr($geoJson, 0, (int) (strlen($geoJson) * 0.7));

        $this->expectException(JsonException::class);
        iterator_to_array(FeatureStream::read(self::stream($truncated), 1024), false);
    }
}
