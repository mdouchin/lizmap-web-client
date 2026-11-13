<?php

/**
 * A list of values stored as JSON in a temporary stream.
 *
 * @author    3liz
 * @copyright 2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Json;

/**
 * Store a long list of values already encoded in JSON.
 *
 * Each value is encoded when it is added. The encoded values are buffered in
 * a string, then written in a temporary stream kept in memory up to
 * MAX_MEMORY bytes, and in a temporary file beyond. So the memory used does
 * not depend on the number of values.
 *
 * The column can be written several times to an output stream with writeTo(),
 * or decoded with jsonSerialize(). It can also be created from a JSON array
 * already encoded, with fromJson().
 */
class JsonColumn implements \Countable, \JsonSerializable
{
    /** Size of the string buffer written at once in the stream */
    private const BUFFER_SIZE = 65536;

    /** Size of the temporary stream kept in memory */
    private const MAX_MEMORY = 262144;

    /** @var null|resource The temporary stream, created at the first flush */
    private $stream;

    /** @var string The encoded values not yet written in the stream */
    private string $buffer = '';

    /** @var int The number of values */
    private int $count = 0;

    /**
     * Close the temporary stream, which deletes its temporary file if any.
     */
    public function __destruct()
    {
        if ($this->stream !== null) {
            fclose($this->stream);
        }
    }

    /**
     * Create a column from a JSON array already encoded, for example by PostgreSQL.
     *
     * The values are copied in the temporary stream without being decoded,
     * so a large array does not use more memory than its JSON string.
     *
     * @param string $json  the JSON array
     * @param int    $count the number of values of the array
     *
     * @return self the column
     *
     * @throws \InvalidArgumentException if the JSON is not an array
     */
    public static function fromJson(string $json, int $count): self
    {
        // Only the content of the array is stored, without the brackets
        $json = trim($json);
        if (!str_starts_with($json, '[') || !str_ends_with($json, ']')) {
            throw new \InvalidArgumentException('The JSON must be an array');
        }

        $column = new self();
        $column->count = $count;
        if ($count === 0) {
            return $column;
        }

        // Copy the content by blocks, without copying the whole string
        $column->stream = fopen('php://temp/maxmemory:'.self::MAX_MEMORY, 'w+');
        $end = strlen($json) - 1;
        for ($offset = 1; $offset < $end; $offset += self::BUFFER_SIZE) {
            fwrite($column->stream, substr($json, $offset, min(self::BUFFER_SIZE, $end - $offset)));
        }

        return $column;
    }

    /**
     * Add a value at the end of the column.
     *
     * @param mixed $value the value, which must be encodable in JSON
     */
    public function append(mixed $value): void
    {
        // Encode the value after a separator, except for the first one
        if ($this->count > 0) {
            $this->buffer .= ',';
        }
        $this->buffer .= json_encode($value, JsonStreamWriter::JSON_FLAGS);
        ++$this->count;

        // Write the buffer in the stream when it is full
        if (strlen($this->buffer) >= self::BUFFER_SIZE) {
            $this->flush();
        }
    }

    /**
     * Get the number of values.
     *
     * @return int the number of values
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Write the column as a JSON array to the output stream.
     *
     * The column is not modified, so it can be written several times.
     *
     * @param resource $output the output stream
     */
    public function writeTo($output): void
    {
        fwrite($output, '[');

        // Copy the values already written in the temporary stream
        if ($this->stream !== null) {
            rewind($this->stream);
            stream_copy_to_stream($this->stream, $output);
        }

        // Then the values still in the buffer
        fwrite($output, $this->buffer);
        fwrite($output, ']');
    }

    /**
     * Get the values, decoded from JSON.
     *
     * All the values are loaded in memory: use writeTo() instead
     * to keep a low memory usage.
     *
     * @return array the values
     */
    public function jsonSerialize(): array
    {
        // Write the column in a memory stream, then decode it
        $output = fopen('php://memory', 'r+');
        $this->writeTo($output);
        rewind($output);
        $values = json_decode(stream_get_contents($output), true);
        fclose($output);

        return $values;
    }

    /**
     * Write the buffer at the end of the temporary stream and empty it.
     */
    private function flush(): void
    {
        // Create the temporary stream, or go back to its end
        // as writeTo() may have moved the position
        if ($this->stream === null) {
            $this->stream = fopen('php://temp/maxmemory:'.self::MAX_MEMORY, 'w+');
        } else {
            fseek($this->stream, 0, SEEK_END);
        }

        fwrite($this->stream, $this->buffer);
        $this->buffer = '';
    }
}
