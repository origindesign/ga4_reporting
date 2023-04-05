<?php
/**
 * @file Contains \Drupal\ga4_reporting\GoogleAnalyticsAPI
 */

namespace Drupal\ga4_reporting;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\MetricType;
use Google\Analytics\Data\V1beta\NumericValue;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\RunReportResponse;


class GoogleAnalyticsDataAPI{



  protected $client;
  protected $propertyId;



  public function initializeClient($credentialsFilePath, $propertyId) {

    $this->propertyId = $propertyId;

    $this->client = new BetaAnalyticsDataClient([
      'credentials' => $credentialsFilePath
    ]);

  }



  /**
   *
   * @param $pageUrl, $dateFrom, $dateTo
   * @param $dateFrom, $dateTo
   * @param $dateTo
   * @return
   */

  public function getPageMetrics($pageUrl, $dateFrom, $dateTo){
    $dateRange = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange->setStartDate($dateFrom);
    $dateRange->setEndDate($dateTo);
    $metricKeys= [
      'ga:pageviews' => 'pageviews',
      'ga:users' => 'users',
      'ga:avgTimeOnPage' => 'avgTimeOnPage',
      'ga:bounceRate' => 'bounceRate'
    ];
    $metrics = [];
    foreach($metricKeys as $metric => $alias) {
      $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
      $reportingMetric->setExpression($metric);
      $reportingMetric->setAlias($alias);
      array_push($metrics, $reportingMetric);
    }
    $filters = new Google_Service_AnalyticsReporting_DimensionFilter();
    $filters->setDimensionName('ga:pagepath');
    $filters->setExpressions([$pageUrl]);
    $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
    $filterClause->setFilters([$filters]);
    $res = $this->performRequest([
      'dateRange' => $dateRange,
      'metrics' => $metrics,
      'filterClause' => $filterClause
    ]);
    $headers = $res['modelData']['reports'][0]['columnHeader']['metricHeader']['metricHeaderEntries'];
    $metrics = $res['modelData']['reports'][0]['data']['rows'][0]['metrics'][0]['values'];
    $resultSet = [];
    for($i = 0; $i < count($headers); $i++) {
      $headerName = $headers[$i]['name'];
      $columnType = $headers[$i]['type'];
      if ($columnType == 'INTEGER') {
        $metricValue = intval($metrics[$i]);
      } elseif ($columnType == 'FLOAT' ||
        $columnType == 'TIME' ||
        $columnType == 'PERCENT') {
        $metricValue = floatval($metrics[$i]);
      } else {
        $metricValue = $metrics[$i];
      }
      $resultSet[$headerName] = $metricValue;
    }
    return $resultSet;
  }



  /**
   *
   * @param $pageUrl, $dateFrom, $dateTo
   * @param $dateFrom, $dateTo
   * @param $dateTo
   * @return
   */

  public function getPageViewsByTrafficSource($pageUrl, $dateFrom, $dateTo) {
    $dateRange = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange->setStartDate($dateFrom);
    $dateRange->setEndDate($dateTo);
    $metricKeys= [
      'ga:pageviews' => 'pageviews'
    ];
    $metrics = [];
    foreach($metricKeys as $metric => $alias) {
      $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
      $reportingMetric->setExpression($metric);
      $reportingMetric->setAlias($alias);
      array_push($metrics, $reportingMetric);
    }
    $filters = new Google_Service_AnalyticsReporting_DimensionFilter();
    $filters->setDimensionName('ga:pagepath');
    $filters->setExpressions([$pageUrl]);

    $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
    $filterClause->setFilters([$filters]);

    $channelGroupingDimension = new Google_Service_AnalyticsReporting_Dimension();
    $channelGroupingDimension->setName('ga:channelGrouping');

    $res = $this->performRequest([
      'dateRange' => $dateRange,
      'metrics' => $metrics,
      'filterClause' => $filterClause,
      'dimensions' => [$channelGroupingDimension]
    ]);
    $resultSet = [];
    $rows = $res['modelData']['reports'][0]['data']['rows'];
    foreach($rows as $row) {
      $resultSet[$row['dimensions'][0]] = intval($row['metrics'][0]['values'][0]);
    }
    return $resultSet;
  }



  public function getPageViewsByCountry($pageUrl, $dateFrom, $dateTo) {
    $dateRange = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange->setStartDate($dateFrom);
    $dateRange->setEndDate($dateTo);
    $metricKeys= [
      'ga:pageviews' => 'pageviews'
    ];
    $metrics = [];
    foreach($metricKeys as $metric => $alias) {
      $reportingMetric = new Google_Service_AnalyticsReporting_Metric();
      $reportingMetric->setExpression($metric);
      $reportingMetric->setAlias($alias);
      array_push($metrics, $reportingMetric);
    }
    $filters = new Google_Service_AnalyticsReporting_DimensionFilter();
    $filters->setDimensionName('ga:pagepath');
    $filters->setExpressions([$pageUrl]);
    $filterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
    $filterClause->setFilters([$filters]);
    $countryDimension = new Google_Service_AnalyticsReporting_Dimension();
    $countryDimension->setName('ga:country');
    $res = $this->performRequest([
      'dateRange' => $dateRange,
      'metrics' => $metrics,
      'filterClause' => $filterClause,
      'dimensions' => [$countryDimension],
      'orderBys' => [
        'fieldName' => 'ga:pageviews',
        'sortOrder' => 'DESCENDING'
      ],
      'pageSize' => 10
    ]);
    $rows = $res['reports'][0]['data']['rows'];
    $resultSet = [];
    foreach($rows as $row) {
      $resultSet[$row['dimensions'][0]] = intval($row['metrics'][0]['values'][0]);
    }
    return $resultSet;
  }



  protected function performRequest($params) {
    $request = new Google_Service_AnalyticsReporting_ReportRequest();
    $request->setpropertyId($this->propertyId);
    if (isset($params['dateRange'])) {
      $request->setDateRanges($params['dateRange']);
    }
    if (isset($params['metrics'])) {
      $request->setMetrics($params['metrics']);
    }
    if (isset($params['filterClause'])) {
      $request->setDimensionFilterClauses([$params['filterClause']]);
    }
    if (isset($params['orderBys'])) {
      $request->setOrderBys($params['orderBys']);
    }
    if (isset($params['pageSize'])) {
      $request->setPageSize(10);
    }
    if (isset($params['dimensions'])) {
      $request->setDimensions($params['dimensions']);
    }
    $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
    $body->setReportRequests([$request]);
    return $this->client->reports->batchGet($body);
  }


  /**
   * @param $dateFrom
   * @param $dateTo
   * @param $metricKeys
   * @param $dimensionKeys
   * @param $dimensionFilterKeys
   * @return mixed
   */
  public function runReport($dateFrom, $dateTo, $metricKeys, $dimensionKeys, $dimensionFilterKeys) {

    // Create the DateRange object.
    $dateRanges = new DateRange([
      'start_date' => $dateFrom,
      'end_date' => $dateTo,
    ]);

    // Setup metrics
    $metrics = [];
    foreach($metricKeys as $metric) {
      array_push($metrics, new Metric(['name' => $metric]));
    }

    // Setup Dimensions
    $dimensions = [];
    foreach($dimensionKeys as $dimension) {
      array_push($dimensions,  new Dimension(['name' => $dimension]));
    }

    // Setup dimension filters
    $dimensionFilters = [];
    foreach($dimensionFilterKeys as $name => $value) {
      $filterExpression =  new FilterExpression([
        'filter' => new Filter([
          'field_name' => $name,
          'string_filter' => new StringFilter([
            'value' => $value,
          ])
        ]),
      ]);
      array_push($dimensionFilters, $filterExpression);
    }

    $reportArray = [
      'property' => 'properties/' . $this->propertyId,
      'dateRanges' => [
        $dateRanges
      ],
      'metrics' => $metrics,
    ];
    if(!empty($dimensions)){
      $reportArray['dimensions'] = $dimensions;
    }
    if(!empty($dimensionFilters)){
      $reportArray['dimensionFilter'] = new FilterExpression([
        'and_group' => new FilterExpressionList([
          'expressions' => $dimensionFilters,
        ]),
      ]);
    }

    // Make an API call.
    $response = $this->client->runReport($reportArray);

    return $response;

  }


  /**
   * @param RunReportResponse $response
   * @return array
   */
  public function getResults(RunReportResponse $response) {

    $result = [];

    $dimensionHeaders = $response->getDimensionHeaders();
    $metricHeaders = $response->getMetricHeaders();
    $rows = $response->getRows();

    for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
      $row = $rows[ $rowIndex ];
      $dimensions = $row->getDimensionValues();
      $metrics = $row->getMetricValues();

      foreach ($dimensions as $key => $dimension) {
        $result[$rowIndex][$dimensionHeaders[$key]->getName()] = $dimension->getValue();
      }

      foreach ($metrics as $key => $metric) {
        $result[$rowIndex][$metricHeaders[$key]->getName()] = $metric->getValue();
      }

    }

    return $result;

  }



}
