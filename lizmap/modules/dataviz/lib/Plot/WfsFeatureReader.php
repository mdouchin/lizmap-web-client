<?php

/**
 * Read the features of a layer with a WFS GetFeature request.
 *
 * @author    3liz
 * @copyright 2017-2026 3liz
 *
 * @see      https://3liz.com
 *
 * @license    Mozilla Public License
 */

namespace LizmapDataviz\Plot;

use GuzzleHttp\Psr7\StreamWrapper;
use Lizmap\Project\Project;
use Lizmap\Request\WFSRequest;
use LizmapDataviz\GeoJson\FeatureStream;

class WfsFeatureReader
{
    protected Project $project;

    protected string $layerId;

    public function __construct(Project $project, string $layerId)
    {
        $this->project = $project;
        $this->layerId = $layerId;
    }

    /**
     * Request the layer features without geometry.
     *
     * @param list<string> $propertyNames the fields to get
     * @param string       $expFilter     a QGIS expression to filter the features
     * @param list<string> $sortBy        the fields to sort the features
     *
     * @return null|iterable the GeoJSON features, decoded by blocks from the
     *                       response stream, null if the request failed
     */
    public function read(array $propertyNames, string $expFilter = '', array $sortBy = array()): ?iterable
    {
        /** @var null|\qgisVectorLayer $layer */
        $layer = $this->project->getLayer($this->layerId);
        if (!$layer) {
            return null;
        }

        $params = array(
            'SERVICE' => 'WFS',
            'VERSION' => '1.0.0',
            'REQUEST' => 'GetFeature',
            'TYPENAME' => $layer->getWfsTypeName(),
            'OUTPUTFORMAT' => 'GeoJSON',
            'GEOMETRYNAME' => 'none',
        );
        if ($expFilter !== '') {
            // Add the fields used in the filter in PROPERTYNAME
            // bug in QGIS SERVER 2.18: send no data if fields in exp_filter not in PROPERTYNAME
            if (preg_match_all('#"([^"]+)"#', $expFilter, $matches)) {
                $propertyNames = array_merge($propertyNames, $matches[1]);
            }
            $params['EXP_FILTER'] = $expFilter;
        }
        $params['PROPERTYNAME'] = implode(',', array_unique(array_filter($propertyNames)));
        if ($sortBy) {
            $params['SORTBY'] = implode(',', $sortBy);
        }

        $request = new WFSRequest($this->project, $params, \lizmap::getServices());
        $response = $request->process();

        if ($response->getCode() >= 400) {
            return null;
        }
        if (in_array(strtolower($response->getMime()), array('text/html', 'text/xml'))) {
            return null;
        }

        $stream = StreamWrapper::getResource($response->getBodyAsStream());

        return FeatureStream::read($stream);
    }
}
