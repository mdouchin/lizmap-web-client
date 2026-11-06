<?php

/**
 * Read the features of a GeoJSON FeatureCollection stream by blocks.
 *
 * @author    3liz
 * @copyright 2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\GeoJson;

/**
 * Decode the features of a GeoJSON FeatureCollection stream with the native
 * json_decode, by blocks of features, so the memory stays bounded.
 *
 * The stream is read by chunks. In each chunk, the block of complete features
 * is found by searching backward for a separator between two features:
 * a "}" followed by a "," and a "{" (whitespaces allowed). This works for the
 * GeoJSON written by QGIS Server and by the PostgreSQL direct query of Lizmap.
 *
 * Such a separator can also be found inside a feature (in a string or between
 * nested objects): the block is then not valid JSON, json_decode fails and the
 * previous separator is tried. As a block must start and end at feature
 * boundaries to be valid JSON, wrong features can not be returned.
 */
class FeatureStream
{
    /**
     * Maximum number of separators tried in a chunk before reading the next one.
     */
    private const MAX_CUT_ATTEMPTS = 8;

    /**
     * Maximum number of commas examined in a chunk to find a separator.
     */
    private const MAX_COMMAS = 4096;

    /**
     * Maximum number of whitespaces around a separator.
     */
    private const MAX_WHITESPACES = 64;

    /**
     * Get the features of the GeoJSON stream.
     *
     * @param resource $stream    the GeoJSON FeatureCollection stream
     * @param int      $chunkSize the number of bytes read at once
     *
     * @return \Generator<int, object> the features, as objects
     *
     * @throws \JsonException if the GeoJSON is not valid
     */
    public static function read($stream, int $chunkSize = 32768): \Generator
    {
        // Find the beginning of the features array
        $buffer = '';
        $start = false;
        while ($start === false) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                // No features array: decode the whole content
                $collection = json_decode($buffer, false, 512, JSON_THROW_ON_ERROR);
                foreach ($collection->features ?? array() as $feature) {
                    yield $feature;
                }

                return;
            }
            $buffer .= $chunk;
            $start = self::findFeaturesStart($buffer);
        }
        $buffer = substr($buffer, $start);

        while (true) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;

            $features = self::decodeCompleteFeatures($buffer);
            if ($features !== null) {
                foreach ($features as $feature) {
                    yield $feature;
                }
                unset($features);
            }
        }

        // The buffer contains the last features, the end of the features
        // array and of the collection, and maybe other collection members
        $collection = json_decode('{"features":['.$buffer, false, 512, JSON_THROW_ON_ERROR);
        foreach ($collection->features as $feature) {
            yield $feature;
        }
    }

    /**
     * @return false|int the position after the "[" of the features array
     */
    private static function findFeaturesStart(string $buffer): false|int
    {
        $key = strpos($buffer, '"features"');
        if ($key === false) {
            return false;
        }
        $bracket = strpos($buffer, '[', $key + 10);
        if ($bracket === false) {
            return false;
        }

        return $bracket + 1;
    }

    /**
     * Decode the complete features at the beginning of the buffer
     * and remove them from the buffer.
     *
     * @param string $buffer the features, the first one starts the buffer
     *
     * @return null|array the decoded features, null if no block can be decoded yet
     */
    private static function decodeCompleteFeatures(string &$buffer): ?array
    {
        $offset = strlen($buffer);
        for ($attempt = 0; $attempt < self::MAX_CUT_ATTEMPTS; ++$attempt) {
            $comma = self::findSeparatorBefore($buffer, $offset);
            if ($comma === false) {
                return null;
            }
            $features = json_decode('['.substr($buffer, 0, $comma).']');
            if (is_array($features)) {
                $buffer = substr($buffer, $comma + 1);

                return $features;
            }
            $offset = $comma;
        }

        return null;
    }

    /**
     * Find, backward from the offset, a comma between "}" and "{".
     *
     * @return false|int the position of the comma
     */
    private static function findSeparatorBefore(string $buffer, int $offset): false|int
    {
        for ($commas = 0; $offset > 0 && $commas < self::MAX_COMMAS; ++$commas) {
            $comma = strrpos($buffer, ',', $offset - strlen($buffer) - 1);
            if ($comma === false) {
                return false;
            }
            $offset = $comma;

            $before = rtrim(substr($buffer, max(0, $comma - self::MAX_WHITESPACES), min(self::MAX_WHITESPACES, $comma)));
            if (!str_ends_with($before, '}')) {
                continue;
            }
            $after = ltrim(substr($buffer, $comma + 1, self::MAX_WHITESPACES));
            if (!str_starts_with($after, '{')) {
                continue;
            }

            return $comma;
        }

        return false;
    }
}
