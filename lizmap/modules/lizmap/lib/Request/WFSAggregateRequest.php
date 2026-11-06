<?php

/**
 * Aggregate the features of a PostgreSQL layer directly in the database.
 *
 * @author    3liz
 * @copyright 2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace Lizmap\Request;

use Lizmap\Project\Project;

/**
 * Compute aggregates (GROUP BY) on the features of a layer with a direct
 * SQL query to PostgreSQL, instead of getting all the features with WFS.
 *
 * The features are selected exactly as with a WFS GetFeature request:
 * the same checks of the layer publication and access rights, and the same
 * SQL WHERE clause (datasource filter, BBOX, EXP_FILTER, filter by login,
 * filter by polygon, FEATUREID).
 *
 * The aggregation is not always possible: not a PostgreSQL layer, OGC FILTER
 * parameter, expression filter not translatable to SQL, numeric function on
 * a non numeric field, database error... In these cases, aggregate() returns
 * null and the caller must use a GetFeature request instead. The reason is
 * given by getFailureReason().
 *
 * Usage:
 *     $request = new WFSAggregateRequest($project, array(
 *         'TYPENAME' => 'my_layer',
 *         'EXP_FILTER' => '"type" = \'A\'',
 *     ), $services);
 *     $rows = $request->aggregate(
 *         array('category'),
 *         array(array('sum', 'value'), array('count', null)),
 *     );
 *     // $rows = array(array(array('A'), array(12.5, 3)), ...)
 */
class WFSAggregateRequest extends WFSRequest
{
    /** Number of features */
    public const COUNT = 'count';

    /** Sum of the numeric values, 0 if none */
    public const SUM = 'sum';

    /** Mean of the numeric values */
    public const AVG = 'avg';

    /** Minimum of the numeric values */
    public const MIN = 'min';

    /** Maximum of the numeric values */
    public const MAX = 'max';

    /** Population standard deviation of the numeric values */
    public const STDDEV = 'stddev';

    /** Median of the numeric values */
    public const MEDIAN = 'median';

    /** Value of the first feature */
    public const FIRST = 'first';

    /** Value of the last feature */
    public const LAST = 'last';

    /** First value which is not null nor an empty string */
    public const FIRST_NOT_EMPTY = 'first_not_empty';

    /** Order the groups by their values */
    public const ORDER_BY_GROUP = 'group';

    /** Order the groups by their first feature */
    public const ORDER_BY_FIRST_FEATURE = 'first_feature';

    /**
     * Aggregate functions which only use numeric values.
     */
    private const NUMERIC_FUNCTIONS = array(
        self::SUM,
        self::AVG,
        self::MIN,
        self::MAX,
        self::STDDEV,
        self::MEDIAN,
    );

    /**
     * All the supported aggregate functions.
     */
    private const FUNCTIONS = array(
        self::COUNT,
        self::SUM,
        self::AVG,
        self::MIN,
        self::MAX,
        self::STDDEV,
        self::MEDIAN,
        self::FIRST,
        self::LAST,
        self::FIRST_NOT_EMPTY,
    );

    /**
     * @var null|string Why the last aggregation was not possible
     */
    protected $failureReason;

    /**
     * Create the request.
     *
     * @param Project         $project  the Lizmap project
     * @param array           $params   the WFS GetFeature parameters selecting the features:
     *                                  TYPENAME (mandatory), EXP_FILTER, BBOX, FEATUREID...
     * @param \lizmapServices $services the Lizmap services
     */
    public function __construct($project, $params, $services)
    {
        // Select the features as a GetFeature request, so the filter
        // by login is added by parameters()
        $params['service'] = 'WFS';
        $params['request'] = 'GetFeature';
        parent::__construct($project, $params, $services);
    }

    /**
     * Get the reason why the last aggregation was not possible.
     *
     * @return null|string the reason, null if the aggregation succeeded
     */
    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * Aggregate the features by group.
     *
     * Each aggregate is a function and a field name. The field is not used by
     * the count function. The numeric functions (sum, avg, min, max, stddev,
     * median) need a numeric field.
     *
     * The groups with a null value for one of the $notNullFields are ignored.
     *
     * @param list<string>                       $groupFields   the fields to group by
     * @param list<array{0: string, 1: ?string}> $aggregates    the aggregates: function and field
     * @param string                             $order         the order of the groups, ORDER_BY_GROUP or ORDER_BY_FIRST_FEATURE
     * @param list<string>                       $notNullFields the group fields which must not be null
     *
     * @return null|list<array{0: list<mixed>, 1: list<mixed>}> for each group, the group values and the
     *                                                          aggregated values, in the order of the parameters;
     *                                                          null if the aggregation is not possible
     */
    public function aggregate(array $groupFields, array $aggregates, string $order = self::ORDER_BY_GROUP, array $notNullFields = array()): ?array
    {
        $this->failureReason = null;

        try {
            // Check the layer and get its fields
            $fields = $this->getAggregatableFields();
            if ($fields === null) {
                return null;
            }

            // Check the requested fields and functions
            if (!$this->validateAggregation($fields, $groupFields, $aggregates, $notNullFields)) {
                return null;
            }

            // Build the WHERE clause, as for a GetFeature request
            $cnx = $this->qgisLayer->getDatasourceConnection();
            $whereClause = $this->getWhereClause($cnx, $this->parameters());
            if ($whereClause === false) {
                return $this->fail('the filter can not be translated to SQL');
            }

            // Run the query
            $sql = $this->buildAggregateSql($cnx, $whereClause, $groupFields, $aggregates, $order, $notNullFields);
            $result = $cnx->query($sql)->fetch();
        } catch (\Throwable $e) {
            $this->appContext->logException($e, 'lizmapadmin');

            return $this->fail('database error: '.$e->getMessage());
        }

        // No group: json_agg returns null
        if (!is_object($result) || $result->groups === null) {
            return array();
        }

        return json_decode($result->groups, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Check that the layer can be aggregated with SQL, and get its fields.
     *
     * The conditions are the ones used by process_getfeature() to request
     * PostgreSQL directly instead of QGIS Server.
     *
     * @return null|array<string, \jDbFieldProperties> the database fields
     *                                                 published in WFS, null if not possible
     */
    protected function getAggregatableFields(): ?array
    {
        // The layer must be published in WFS and visible by the user
        $typenameCheckingCode = $this->checkingTypename();
        if ($typenameCheckingCode !== 200) {
            return $this->fail('the layer is not available (HTTP code '.$typenameCheckingCode.')');
        }

        // Get the layer
        $lizmapLayer = $this->project->findLayerByTypeName($this->requestedTypename());
        if (!$lizmapLayer) {
            return $this->fail('the layer is not found');
        }
        $layer = $this->project->getLayer($lizmapLayer->id);

        // Only PostgreSQL tables, without OGC filter or forced QGIS Server request
        if (!$layer instanceof \qgisVectorLayer || $layer->getProvider() != 'postgres') {
            return $this->fail('the layer is not a PostgreSQL layer');
        }
        $this->qgisLayer = $layer;
        if (!empty($this->param('filter', ''))) {
            return $this->fail('OGC FILTER parameter is not supported');
        }
        if ($this->param('force_qgis', '') == '1') {
            return $this->fail('QGIS Server is forced');
        }
        // A table, not a SQL query: table="(SELECT ...)"
        $this->datasource = $this->qgisLayer->getDatasourceParameters();
        $table = (string) ($this->datasource->table ?? '');
        if ($table === '' || $table[0] == '(') {
            return $this->fail('the layer datasource is not a table');
        }

        // The primary key orders the features
        $dbFields = $this->qgisLayer->getDbFieldList();
        foreach (explode(',', $this->datasource->key) as $key) {
            if (!array_key_exists($key, $dbFields)) {
                return $this->fail('the primary key is not a table field');
            }
        }

        // Only the fields published in WFS can be used
        $fields = array();
        foreach ($this->qgisLayer->getWfsFields() as $field) {
            if (array_key_exists($field, $dbFields)) {
                $fields[$field] = $dbFields[$field];
            }
        }

        return $fields;
    }

    /**
     * Check the fields and the functions of the aggregation.
     *
     * The field names are checked against the fields published in WFS:
     * they are safe to be used in the SQL query once enclosed.
     *
     * @param array<string, \jDbFieldProperties> $fields        the available fields
     * @param list<string>                       $groupFields   the fields to group by
     * @param list<array{0: string, 1: ?string}> $aggregates    the aggregates: function and field
     * @param list<string>                       $notNullFields the group fields which must not be null
     *
     * @return bool true if the aggregation is valid
     */
    protected function validateAggregation(array $fields, array $groupFields, array $aggregates, array $notNullFields): bool
    {
        // At least one group field, all available
        if (count($groupFields) === 0) {
            return (bool) $this->fail('no group field');
        }
        foreach (array_merge($groupFields, $notNullFields) as $field) {
            if (!array_key_exists($field, $fields)) {
                return (bool) $this->fail('the field "'.$field.'" is not available');
            }
        }

        foreach ($aggregates as $aggregate) {
            list($function, $field) = $aggregate;

            // Supported function only
            if (!in_array($function, self::FUNCTIONS, true)) {
                return (bool) $this->fail('unknown aggregate function "'.$function.'"');
            }

            // The count does not use a field
            if ($function === self::COUNT) {
                continue;
            }
            if ($field === null || !array_key_exists($field, $fields)) {
                return (bool) $this->fail('the field "'.$field.'" is not available');
            }

            // The numeric functions need a numeric field: the values of a text
            // field must be converted, which can only be done outside SQL
            if (in_array($function, self::NUMERIC_FUNCTIONS, true) && !self::isNumericField($fields[$field])) {
                return (bool) $this->fail('the field "'.$field.'" is not numeric');
            }
        }

        return true;
    }

    /**
     * Build the SQL query aggregating the features.
     *
     * The query returns one row with one column "groups": a JSON array with,
     * for each group, the array of the group values and the array of the
     * aggregated values. JSON keeps the types of the values (number, string,
     * boolean).
     *
     * @param \jDbConnection                     $cnx           the database connection
     * @param string                             $whereClause   the WHERE clause selecting the features
     * @param list<string>                       $groupFields   the fields to group by, already validated
     * @param list<array{0: string, 1: ?string}> $aggregates    the aggregates, already validated
     * @param string                             $order         the order of the groups
     * @param list<string>                       $notNullFields the group fields which must not be null
     *
     * @return string the SQL query
     */
    protected function buildAggregateSql($cnx, string $whereClause, array $groupFields, array $aggregates, string $order, array $notNullFields): string
    {
        // Order of the features, given by the primary key: it is used by the
        // first and last functions and to order the groups by first feature
        $keys = array_map(array($cnx, 'encloseName'), explode(',', $this->datasource->key));
        $featureOrder = implode(', ', $keys);

        // Source: the selected features, with only the needed fields
        $sourceFields = array();
        foreach ($groupFields as $field) {
            $sourceFields[] = $cnx->encloseName($field);
        }
        foreach ($aggregates as $aggregate) {
            if ($aggregate[1] !== null) {
                $sourceFields[] = $cnx->encloseName($aggregate[1]);
            }
        }
        $sourceFields = array_unique($sourceFields);
        $sourceFields[] = 'row_number() OVER (ORDER BY '.$featureOrder.') AS "lizmap_feature_order"';

        // Ignore the features with null values for some group fields
        foreach ($notNullFields as $field) {
            $whereClause .= ' AND '.$cnx->encloseName($field).' IS NOT NULL';
        }

        // Group values, aliased g0, g1...
        $groupColumns = array();
        $groupAliases = array();
        foreach ($groupFields as $i => $field) {
            $groupColumns[] = $cnx->encloseName($field).' AS "g'.$i.'"';
            $groupAliases[] = '"g'.$i.'"';
        }

        // Aggregated values, aliased a0, a1...
        $aggregateColumns = array();
        $aggregateAliases = array();
        foreach ($aggregates as $i => $aggregate) {
            $aggregateColumns[] = $this->getAggregateSql($cnx, $aggregate[0], $aggregate[1]).' AS "a'.$i.'"';
            $aggregateAliases[] = '"a'.$i.'"';
        }

        // Order of the groups: by their values, or by their first feature
        $groupOrder = '"lizmap_group_order"';
        if ($order === self::ORDER_BY_GROUP) {
            $groupOrder = implode(', ', $groupAliases);
        }

        // Group the features, then build one JSON array of all the groups
        $positions = implode(', ', range(1, count($groupFields)));

        return '
            WITH source AS (
                SELECT '.implode(', ', $sourceFields).'
                FROM '.$this->datasource->table.'
                '.$whereClause.'
            ),
            grouped AS (
                SELECT '.implode(', ', array_merge($groupColumns, $aggregateColumns)).',
                    min("lizmap_feature_order") AS "lizmap_group_order"
                FROM source
                GROUP BY '.$positions.'
            )
            SELECT json_agg(
                json_build_array(
                    json_build_array('.implode(', ', $groupAliases).'),
                    json_build_array('.implode(', ', $aggregateAliases).')
                )
                ORDER BY '.$groupOrder.'
            )::text AS groups
            FROM grouped
        ';
    }

    /**
     * Get the SQL expression of an aggregate function.
     *
     * @param \jDbConnection $cnx      the database connection
     * @param string         $function the aggregate function, already validated
     * @param null|string    $field    the field, already validated
     *
     * @return string the SQL expression
     */
    protected function getAggregateSql($cnx, string $function, ?string $field): string
    {
        $column = $field !== null ? $cnx->encloseName($field) : null;

        switch ($function) {
            case self::COUNT:
                return 'count(*)';

            case self::SUM:
                // 0 instead of null when there is no value
                return 'coalesce(sum('.$column.'), 0)';

            case self::AVG:
            case self::MIN:
            case self::MAX:
                return $function.'('.$column.')';

            case self::STDDEV:
                return 'stddev_pop('.$column.')';

            case self::MEDIAN:
                return 'percentile_cont(0.5) WITHIN GROUP (ORDER BY '.$column.'::double precision)';

            case self::FIRST:
                return '(array_agg('.$column.' ORDER BY "lizmap_feature_order"))[1]';

            case self::LAST:
                return '(array_agg('.$column.' ORDER BY "lizmap_feature_order" DESC))[1]';

            default:
                // FIRST_NOT_EMPTY
                return '(array_agg('.$column.' ORDER BY "lizmap_feature_order")'
                    .' FILTER (WHERE '.$column.' IS NOT NULL AND '.$column.'::text <> \'\'))[1]';
        }
    }

    /**
     * Tell if a database field is numeric.
     *
     * @param \jDbFieldProperties $field the field
     *
     * @return bool true if the field values are numbers
     */
    protected static function isNumericField($field): bool
    {
        // jDb gives the PostgreSQL type name without its size: int2, int4 and
        // int8 are "int", float4 and float8 are "float". The unified type is
        // not used, as it considers intervals as integers.
        return in_array($field->type, array('int', 'float', 'numeric'), true);
    }

    /**
     * Store the reason why the aggregation is not possible.
     *
     * It always returns null, so the callers can return its result directly.
     *
     * @param string $reason the reason
     */
    protected function fail(string $reason): null
    {
        $this->failureReason = $reason;

        return null;
    }
}
