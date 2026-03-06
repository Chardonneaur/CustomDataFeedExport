<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CustomDataFeedExport;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;

/**
 * API for CustomDataFeedExport plugin
 *
 * Allows creating data feed configurations and exporting visit log data as CSV
 *
 * @method static API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    private const MAX_FILTER_LIMIT = 100000;
    private const DEFAULT_FILTER_LIMIT = 1000;
    private const MAX_FILTERS = 20;
    private const MAX_REGEX_PATTERN_LENGTH = 128;
    private const MAX_DIMENSIONS = 50;

    /**
     * Available dimensions for data feeds
     */
    public static function getAvailableDimensions()
    {
        return [
            // Visit-level dimensions
            'idVisit' => 'Visit ID',
            'visitorId' => 'Visitor ID',
            'visitIp' => 'IP Address',
            'fingerprint' => 'Fingerprint',
            'userId' => 'User ID',
            'serverDate' => 'Server Date',
            'serverTimePretty' => 'Server Time',
            'serverDatePretty' => 'Server Date (Pretty)',
            'visitDuration' => 'Visit Duration (seconds)',
            'visitDurationPretty' => 'Visit Duration',
            'actions' => 'Number of Actions',
            'interactions' => 'Number of Interactions',
            'referrerType' => 'Referrer Type',
            'referrerTypeName' => 'Referrer Type Name',
            'referrerName' => 'Referrer Name',
            'referrerKeyword' => 'Referrer Keyword',
            'referrerKeywordPosition' => 'Keyword Position',
            'referrerUrl' => 'Referrer URL',
            'referrerSearchEngineUrl' => 'Search Engine URL',
            'referrerSearchEngineIcon' => 'Search Engine Icon',
            'referrerSocialNetworkUrl' => 'Social Network URL',
            'referrerSocialNetworkIcon' => 'Social Network Icon',
            'language' => 'Language',
            'languageCode' => 'Language Code',
            'deviceType' => 'Device Type',
            'deviceTypeIcon' => 'Device Type Icon',
            'deviceBrand' => 'Device Brand',
            'deviceModel' => 'Device Model',
            'operatingSystem' => 'Operating System',
            'operatingSystemName' => 'OS Name',
            'operatingSystemIcon' => 'OS Icon',
            'operatingSystemCode' => 'OS Code',
            'operatingSystemVersion' => 'OS Version',
            'browserFamily' => 'Browser Family',
            'browserFamilyDescription' => 'Browser Family Description',
            'browser' => 'Browser',
            'browserName' => 'Browser Name',
            'browserIcon' => 'Browser Icon',
            'browserCode' => 'Browser Code',
            'browserVersion' => 'Browser Version',
            'screenType' => 'Screen Type',
            'resolution' => 'Resolution',
            'plugins' => 'Plugins',
            'pluginsIcons' => 'Plugin Icons',
            'continent' => 'Continent',
            'continentCode' => 'Continent Code',
            'country' => 'Country',
            'countryCode' => 'Country Code',
            'countryFlag' => 'Country Flag',
            'region' => 'Region',
            'regionCode' => 'Region Code',
            'city' => 'City',
            'location' => 'Location',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'visitLocalTime' => 'Local Time',
            'visitLocalHour' => 'Local Hour',
            'daysSinceFirstVisit' => 'Days Since First Visit',
            'daysSinceLastVisit' => 'Days Since Last Visit',
            'visitCount' => 'Visit Count',
            'goalConversions' => 'Goal Conversions',
            'siteName' => 'Site Name',
            'siteCurrency' => 'Site Currency',
            'totalEcommerceRevenue' => 'Ecommerce Revenue',
            'totalEcommerceConversions' => 'Ecommerce Conversions',
            'totalEcommerceItems' => 'Ecommerce Items',
            'totalAbandonedCartsRevenue' => 'Abandoned Carts Revenue',
            'totalAbandonedCarts' => 'Abandoned Carts',
            'totalAbandonedCartsItems' => 'Abandoned Carts Items',
            'events' => 'Events Count',
            'searches' => 'Site Searches Count',
            'entryPageTitle' => 'Entry Page Title',
            'entryPageUrl' => 'Entry Page URL',
            'exitPageTitle' => 'Exit Page Title',
            'exitPageUrl' => 'Exit Page URL',

            // Action-level dimensions (one row per action)
            'actionType' => '[Action] Type',
            'actionUrl' => '[Action] URL',
            'actionTitle' => '[Action] Page Title',
            'actionTimestamp' => '[Action] Timestamp',
            'actionTimeSpent' => '[Action] Time Spent (seconds)',
            'actionTimeSpentPretty' => '[Action] Time Spent',
            'actionGenerationTime' => '[Action] Generation Time (ms)',
            'actionPosition' => '[Action] Position in Visit',

            // Event dimensions
            'eventCategory' => '[Event] Category',
            'eventAction' => '[Event] Action',
            'eventName' => '[Event] Name',
            'eventValue' => '[Event] Value',

            // Site search dimensions
            'searchKeyword' => '[Search] Keyword',
            'searchCategory' => '[Search] Category',
            'searchCount' => '[Search] Results Count',

            // Download/Outlink dimensions
            'downloadUrl' => '[Download] URL',
            'outlinkUrl' => '[Outlink] URL',

            // Content tracking dimensions
            'contentName' => '[Content] Name',
            'contentPiece' => '[Content] Piece',
            'contentTarget' => '[Content] Target',
            'contentInteraction' => '[Content] Interaction',
        ];
    }

    /**
     * Get list of action-level dimensions
     * These dimensions require iterating through actionDetails
     *
     * @return array
     */
    public static function getActionDimensions()
    {
        return [
            'actionType', 'actionUrl', 'actionTitle', 'actionTimestamp',
            'actionTimeSpent', 'actionTimeSpentPretty', 'actionGenerationTime', 'actionPosition',
            'eventCategory', 'eventAction', 'eventName', 'eventValue',
            'searchKeyword', 'searchCategory', 'searchCount',
            'downloadUrl', 'outlinkUrl',
            'contentName', 'contentPiece', 'contentTarget', 'contentInteraction',
        ];
    }

    /**
     * Get all available dimensions that can be used in feeds
     *
     * @return array
     */
    public function getAvailableDimensionsList()
    {
        Piwik::checkUserHasSomeViewAccess();

        $dimensions = self::getAvailableDimensions();
        $actionDimensions = self::getActionDimensions();
        $result = [];
        foreach ($dimensions as $key => $label) {
            $result[] = [
                'key' => $key,
                'label' => $label,
                'isActionLevel' => in_array($key, $actionDimensions, true),
            ];
        }
        return $result;
    }

    /**
     * Get all feeds for a site
     *
     * @param int $idSite
     * @return array
     */
    public function getFeeds($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $model = new Model();
        $feeds = $model->getAllFeeds($idSite);

        $currentLogin = Piwik::getCurrentUserLogin();
        $isAdmin      = Piwik::isUserHasAdminAccess($idSite);

        foreach ($feeds as &$feed) {
            $feed['dimensions'] = json_decode($feed['dimensions'], true);
            $feed['filters'] = !empty($feed['filters']) ? json_decode($feed['filters'], true) : [];
            // Only expose the token to the feed's owner or a site admin
            if (!$isAdmin && $feed['login'] !== $currentLogin) {
                unset($feed['token']);
            }
            unset($feed['login'], $feed['deleted']);
        }

        return $feeds;
    }

    /**
     * Get a specific feed
     *
     * @param int $idFeed
     * @return array
     * @throws \Exception
     */
    public function getFeed($idFeed)
    {
        $model = new Model();
        $feed = $model->getFeed($idFeed);

        if (empty($feed)) {
            throw new \Exception('Feed not found');
        }

        try {
            Piwik::checkUserHasViewAccess($feed['idsite']);
        } catch (\Exception $e) {
            throw new \Exception('Feed not found');
        }

        $feed['dimensions'] = json_decode($feed['dimensions'], true);
        $feed['filters'] = !empty($feed['filters']) ? json_decode($feed['filters'], true) : [];

        // Only expose the token to the feed's owner or a site admin
        $currentLogin = Piwik::getCurrentUserLogin();
        $isAdmin      = Piwik::isUserHasAdminAccess($feed['idsite']);
        if (!$isAdmin && $feed['login'] !== $currentLogin) {
            unset($feed['token']);
        }
        unset($feed['login'], $feed['deleted']);

        return $feed;
    }

    /**
     * Create a new feed
     *
     * @param int $idSite
     * @param string $name
     * @param array|string $dimensions Array or JSON string of dimensions
     * @param string $description
     * @param array|string $filters Array or JSON string of filters
     * @return int Feed ID
     */
    public function createFeed($idSite, $name, $dimensions = null, $description = '', $filters = null)
    {
        Piwik::checkUserHasWriteAccess($idSite);

        $name = trim($name);
        if (empty($name)) {
            throw new \Exception('Feed name cannot be empty');
        }
        if (strlen($name) > 255) {
            throw new \Exception('Feed name must not exceed 255 characters');
        }
        if (strlen($description) > 500) {
            throw new \Exception('Feed description must not exceed 500 characters');
        }

        // Handle various input formats for dimensions
        if (is_string($dimensions) && !empty($dimensions)) {
            // Decode HTML entities (Matomo sanitizes input values via htmlspecialchars)
            $dimensions = html_entity_decode($dimensions, ENT_QUOTES, 'UTF-8');
            $dimensions = json_decode($dimensions, true);
        }

        if (empty($dimensions) || !is_array($dimensions)) {
            throw new \Exception('At least one dimension is required');
        }

        // Validate dimensions
        if (count($dimensions) > self::MAX_DIMENSIONS) {
            throw new \Exception('Too many dimensions (max ' . self::MAX_DIMENSIONS . ')');
        }
        $availableDimensions = array_keys(self::getAvailableDimensions());
        foreach ($dimensions as $dimension) {
            if (!in_array($dimension, $availableDimensions, true)) {
                throw new \Exception('Invalid dimension');
            }
        }

        $filtersJson = $this->normalizeAndValidateFilters($filters);

        $model = new Model();

        $feedData = [
            'idsite' => $idSite,
            'login' => Piwik::getCurrentUserLogin(),
            'name' => $name,
            'description' => $description,
            'dimensions' => json_encode($dimensions),
            'filters' => $filtersJson,
        ];

        $idFeed = $model->createFeed($feedData);

        return $idFeed;
    }

    /**
     * Update an existing feed
     *
     * @param int $idFeed
     * @param string $name
     * @param array|string $dimensions Array or JSON string of dimensions
     * @param string $description
     * @param array|string $filters Array or JSON string of filters
     * @return bool
     */
    public function updateFeed($idFeed, $name, $dimensions, $description = '', $filters = null)
    {
        $model = new Model();
        $feed = $model->getFeed($idFeed);

        if (empty($feed)) {
            throw new \Exception('Feed not found');
        }

        try {
            Piwik::checkUserHasWriteAccess($feed['idsite']);
        } catch (\Exception $e) {
            throw new \Exception('Feed not found');
        }

        $isOwner = ($feed['login'] === Piwik::getCurrentUserLogin());
        $isAdmin = Piwik::isUserHasAdminAccess($feed['idsite']);
        if (!$isOwner && !$isAdmin) {
            throw new \Exception('Feed not found');
        }

        $name = trim($name);
        if (empty($name)) {
            throw new \Exception('Feed name cannot be empty');
        }
        if (strlen($name) > 255) {
            throw new \Exception('Feed name must not exceed 255 characters');
        }
        if (strlen($description) > 500) {
            throw new \Exception('Feed description must not exceed 500 characters');
        }

        // Handle various input formats for dimensions
        if (is_string($dimensions) && !empty($dimensions)) {
            $dimensions = html_entity_decode($dimensions, ENT_QUOTES, 'UTF-8');
            $dimensions = json_decode($dimensions, true);
        }

        if (empty($dimensions) || !is_array($dimensions)) {
            throw new \Exception('At least one dimension is required');
        }

        // Validate dimensions
        if (count($dimensions) > self::MAX_DIMENSIONS) {
            throw new \Exception('Too many dimensions (max ' . self::MAX_DIMENSIONS . ')');
        }
        $availableDimensions = array_keys(self::getAvailableDimensions());
        foreach ($dimensions as $dimension) {
            if (!in_array($dimension, $availableDimensions, true)) {
                throw new \Exception('Invalid dimension');
            }
        }

        $filtersJson = $this->normalizeAndValidateFilters($filters);

        $model->updateFeed($idFeed, [
            'name' => $name,
            'description' => $description,
            'dimensions' => json_encode($dimensions),
            'filters' => $filtersJson,
        ]);

        return true;
    }

    /**
     * Delete a feed
     *
     * @param int $idFeed
     * @return bool
     */
    public function deleteFeed($idFeed)
    {
        $model = new Model();
        $feed = $model->getFeed($idFeed);

        if (empty($feed)) {
            throw new \Exception('Feed not found');
        }

        try {
            Piwik::checkUserHasWriteAccess($feed['idsite']);
        } catch (\Exception $e) {
            throw new \Exception('Feed not found');
        }

        $isOwner = ($feed['login'] === Piwik::getCurrentUserLogin());
        $isAdmin = Piwik::isUserHasAdminAccess($feed['idsite']);
        if (!$isOwner && !$isAdmin) {
            throw new \Exception('Feed not found');
        }

        $model->deleteFeed($idFeed);

        return true;
    }

    /**
     * Delete all feeds for a site
     *
     * @param int $idSite
     * @return bool
     */
    public function deleteAllFeeds($idSite)
    {
        Piwik::checkUserHasWriteAccess($idSite);

        $model        = new Model();
        $feeds        = $model->getAllFeeds($idSite);
        $currentLogin = Piwik::getCurrentUserLogin();
        $isAdmin      = Piwik::isUserHasAdminAccess($idSite);

        foreach ($feeds as $feed) {
            if ($isAdmin || $feed['login'] === $currentLogin) {
                $model->deleteFeed($feed['idfeed']);
            }
        }

        return true;
    }

    /**
     * Export feed data as CSV
     *
     * @param int $idFeed
     * @param string $period
     * @param string $date
     * @param string|bool $segment
     * @param int $filterLimit
     * @return string CSV content
     */
    public function exportFeed($idFeed, $period = 'day', $date = 'today', $segment = false, $filterLimit = 1000)
    {
        $model = new Model();
        $feed = $model->getFeed($idFeed);

        if (empty($feed)) {
            throw new \Exception('Feed not found');
        }

        try {
            Piwik::checkUserHasViewAccess($feed['idsite']);
        } catch (\Exception $e) {
            throw new \Exception('Feed not found');
        }

        if (!in_array($period, ['day', 'week', 'month', 'year', 'range'], true)) {
            throw new \Exception('Invalid period');
        }
        if (!preg_match('/^(today|yesterday|\d{4}-\d{2}-\d{2}(,\d{4}-\d{2}-\d{2})?)$/', $date)) {
            throw new \Exception('Invalid date format');
        }
        if ($segment !== false && $segment !== '' && strlen((string) $segment) > 4096) {
            throw new \Exception('Segment expression too long');
        }

        $filterLimit = $this->sanitizeFilterLimit($filterLimit);
        return $this->exportFeedInternal($feed, $period, $date, $segment, $filterLimit);
    }

    /**
     * Internal method to export feed data as CSV
     *
     * @param array $feed
     * @param string $period
     * @param string $date
     * @param string|bool $segment
     * @param int $filterLimit
     * @return string CSV content
     */
    private function exportFeedInternal($feed, $period = 'day', $date = 'today', $segment = false, $filterLimit = 1000)
    {
        $dimensions = json_decode($feed['dimensions'], true);
        if (!is_array($dimensions) || empty($dimensions)) {
            throw new \Exception('Feed configuration is invalid');
        }

        // Re-validate stored dimensions against current whitelist (defence-in-depth)
        $availableDimensions = self::getAvailableDimensions();
        $dimensions = array_values(array_filter($dimensions, function ($dim) use ($availableDimensions) {
            return isset($availableDimensions[$dim]);
        }));
        if (empty($dimensions)) {
            throw new \Exception('Feed configuration is invalid');
        }

        $filters = !empty($feed['filters']) ? json_decode($feed['filters'], true) : [];

        // Get visit log data
        $visits = Request::processRequest('Live.getLastVisitsDetails', [
            'idSite' => $feed['idsite'],
            'period' => $period,
            'date' => $date,
            'segment' => $segment,
            'filter_limit' => $filterLimit,
            'doNotFetchActions' => false,
        ]);

        $rows = $visits->getRows();

        // Build CSV
        $csv = [];

        // Header row with dimension labels
        $headers = [];
        foreach ($dimensions as $dimension) {
            $headers[] = $availableDimensions[$dimension] ?? $dimension;
        }
        $csv[] = $this->csvEscapeRow($headers);

        // Check if any action-level dimensions are selected
        $actionDimensions = self::getActionDimensions();
        $hasActionDimensions = !empty(array_intersect($dimensions, $actionDimensions));

        // Data rows
        foreach ($rows as $row) {
            $actionDetails = $row->getColumn('actionDetails');
            if (!is_array($actionDetails)) {
                $actionDetails = [];
            }

            // Get entry page (first action) and exit page (last action)
            $entryPage = null;
            $exitPage = null;
            $pageviews = array_filter($actionDetails, function($action) {
                return isset($action['type']) && $action['type'] === 'action';
            });
            $pageviews = array_values($pageviews);

            if (!empty($pageviews)) {
                $entryPage = $pageviews[0];
                $exitPage = $pageviews[count($pageviews) - 1];
            }

            // Build visit-level row values
            $visitValues = $this->buildVisitValues($row, $entryPage, $exitPage, $availableDimensions);

            if ($hasActionDimensions) {
                // Create one row per action
                if (empty($actionDetails)) {
                    // No actions - create one row with empty action fields
                    $rowValues = array_merge($visitValues, $this->buildActionValues(null, 0));

                    if (!$this->rowMatchesFilters($rowValues, $filters)) {
                        continue;
                    }

                    $rowData = [];
                    foreach ($dimensions as $dimension) {
                        $rowData[] = $rowValues[$dimension] ?? '';
                    }
                    $csv[] = $this->csvEscapeRow($rowData);
                } else {
                    // Create one row for each action
                    foreach ($actionDetails as $actionIndex => $action) {
                        $actionValues = $this->buildActionValues($action, $actionIndex + 1);
                        $rowValues = array_merge($visitValues, $actionValues);

                        if (!$this->rowMatchesFilters($rowValues, $filters)) {
                            continue;
                        }

                        $rowData = [];
                        foreach ($dimensions as $dimension) {
                            $rowData[] = $rowValues[$dimension] ?? '';
                        }
                        $csv[] = $this->csvEscapeRow($rowData);
                    }
                }
            } else {
                // No action dimensions - one row per visit
                if (!$this->rowMatchesFilters($visitValues, $filters)) {
                    continue;
                }

                $rowData = [];
                foreach ($dimensions as $dimension) {
                    $rowData[] = $visitValues[$dimension] ?? '';
                }
                $csv[] = $this->csvEscapeRow($rowData);
            }
        }

        return implode("\n", $csv);
    }

    /**
     * Build visit-level values from a row
     *
     * @param mixed $row
     * @param array|null $entryPage
     * @param array|null $exitPage
     * @param array $availableDimensions
     * @return array
     */
    private function buildVisitValues($row, $entryPage, $exitPage, $availableDimensions)
    {
        $actionDimensions = self::getActionDimensions();
        $values = [];

        foreach ($availableDimensions as $dimKey => $dimLabel) {
            // Skip action-level dimensions
            if (in_array($dimKey, $actionDimensions, true)) {
                continue;
            }

            if ($dimKey === 'entryPageUrl') {
                $values[$dimKey] = $entryPage['url'] ?? '';
            } elseif ($dimKey === 'entryPageTitle') {
                $values[$dimKey] = $entryPage['pageTitle'] ?? '';
            } elseif ($dimKey === 'exitPageUrl') {
                $values[$dimKey] = $exitPage['url'] ?? '';
            } elseif ($dimKey === 'exitPageTitle') {
                $values[$dimKey] = $exitPage['pageTitle'] ?? '';
            } else {
                $value = $row->getColumn($dimKey);
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $values[$dimKey] = $value !== false ? $value : '';
            }
        }

        return $values;
    }

    /**
     * Build action-level values from an action
     *
     * @param array|null $action
     * @param int $position
     * @return array
     */
    private function buildActionValues($action, $position)
    {
        if (empty($action)) {
            return [
                'actionType' => '',
                'actionUrl' => '',
                'actionTitle' => '',
                'actionTimestamp' => '',
                'actionTimeSpent' => '',
                'actionTimeSpentPretty' => '',
                'actionGenerationTime' => '',
                'actionPosition' => '',
                'eventCategory' => '',
                'eventAction' => '',
                'eventName' => '',
                'eventValue' => '',
                'searchKeyword' => '',
                'searchCategory' => '',
                'searchCount' => '',
                'downloadUrl' => '',
                'outlinkUrl' => '',
                'contentName' => '',
                'contentPiece' => '',
                'contentTarget' => '',
                'contentInteraction' => '',
            ];
        }

        $type = $action['type'] ?? '';

        return [
            'actionType' => $type,
            'actionUrl' => $action['url'] ?? '',
            'actionTitle' => $action['pageTitle'] ?? '',
            'actionTimestamp' => $action['timestamp'] ?? '',
            'actionTimeSpent' => $action['timeSpent'] ?? '',
            'actionTimeSpentPretty' => $action['timeSpentPretty'] ?? '',
            'actionGenerationTime' => $action['generationTimeMilliseconds'] ?? '',
            'actionPosition' => $position,

            // Event fields
            'eventCategory' => ($type === 'event') ? ($action['eventCategory'] ?? '') : '',
            'eventAction' => ($type === 'event') ? ($action['eventAction'] ?? '') : '',
            'eventName' => ($type === 'event') ? ($action['eventName'] ?? '') : '',
            'eventValue' => ($type === 'event') ? ($action['eventValue'] ?? '') : '',

            // Site search fields
            'searchKeyword' => ($type === 'search') ? ($action['siteSearchKeyword'] ?? '') : '',
            'searchCategory' => ($type === 'search') ? ($action['siteSearchCategory'] ?? '') : '',
            'searchCount' => ($type === 'search') ? ($action['siteSearchCount'] ?? '') : '',

            // Download URL
            'downloadUrl' => ($type === 'download') ? ($action['url'] ?? '') : '',

            // Outlink URL
            'outlinkUrl' => ($type === 'outlink') ? ($action['url'] ?? '') : '',

            // Content tracking fields
            'contentName' => $action['contentName'] ?? '',
            'contentPiece' => $action['contentPiece'] ?? '',
            'contentTarget' => $action['contentTarget'] ?? '',
            'contentInteraction' => $action['contentInteraction'] ?? '',
        ];
    }

    /**
     * Check if a row matches all filters
     *
     * @param array $rowValues
     * @param array $filters
     * @return bool
     */
    private function rowMatchesFilters($rowValues, $filters)
    {
        if (empty($filters)) {
            return true;
        }

        foreach ($filters as $filter) {
            $dimension = $filter['dimension'] ?? '';
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? '';

            if (empty($dimension)) {
                continue;
            }

            $rowValue = (string)($rowValues[$dimension] ?? '');
            $filterValue = (string)$value;

            $matches = false;
            switch ($operator) {
                case 'equals':
                    $matches = ($rowValue === $filterValue);
                    break;
                case 'not_equals':
                    $matches = ($rowValue !== $filterValue);
                    break;
                case 'contains':
                    $matches = (stripos($rowValue, $filterValue) !== false);
                    break;
                case 'not_contains':
                    if ($filterValue === '') {
                        $matches = true;
                        break;
                    }
                    $matches = (stripos($rowValue, $filterValue) === false);
                    break;
                case 'starts_with':
                    $matches = (stripos($rowValue, $filterValue) === 0);
                    break;
                case 'ends_with':
                    if ($filterValue === '') {
                        $matches = true;
                        break;
                    }
                    $matches = (substr(strtolower($rowValue), -strlen($filterValue)) === strtolower($filterValue));
                    break;
                case 'regex':
                    if (!self::isSafeRegexPattern($filterValue)) {
                        $matches = false;
                        break;
                    }
                    $matches = (@preg_match('~' . $filterValue . '~i', $rowValue) === 1);
                    break;
                case 'not_regex':
                    if (!self::isSafeRegexPattern($filterValue)) {
                        $matches = false;
                        break;
                    }
                    $matches = (@preg_match('~' . $filterValue . '~i', $rowValue) !== 1);
                    break;
                case 'is_empty':
                    $matches = ($rowValue === '' || $rowValue === null);
                    break;
                case 'is_not_empty':
                    $matches = ($rowValue !== '' && $rowValue !== null);
                    break;
                default:
                    $matches = false;
            }

            // All filters must match (AND logic)
            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Escape a row for CSV output
     *
     * @param array $row
     * @return string
     */
    private function csvEscapeRow($row)
    {
        $escaped = [];
        foreach ($row as $value) {
            if ($value === null) {
                $value = '';
            }
            $value = (string) $value;

            // Prevent formula injection in spreadsheet software.
            if (preg_match('/^[\x00-\x20]*[=\+\-@\|]/', $value)) {
                $value = "'" . $value;
            }

            $value = str_replace('"', '""', $value);
            if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
                $value = '"' . $value . '"';
            }
            $escaped[] = $value;
        }
        return implode(',', $escaped);
    }

    private function sanitizeFilterLimit($filterLimit)
    {
        $filterLimit = (int) $filterLimit;
        if ($filterLimit <= 0) {
            return self::DEFAULT_FILTER_LIMIT;
        }
        return min($filterLimit, self::MAX_FILTER_LIMIT);
    }

    private function normalizeAndValidateFilters($filters)
    {
        if (empty($filters)) {
            return null;
        }

        if (is_string($filters)) {
            $filters = html_entity_decode($filters, ENT_QUOTES, 'UTF-8');
            $filters = json_decode($filters, true);
        }

        if (!is_array($filters)) {
            throw new \Exception('Invalid filters format');
        }

        if (count($filters) > self::MAX_FILTERS) {
            throw new \Exception('Too many filters (max ' . self::MAX_FILTERS . ')');
        }

        $availableDimensions = array_keys(self::getAvailableDimensions());
        $allowedOperators = [
            'equals', 'not_equals', 'contains', 'not_contains',
            'starts_with', 'ends_with', 'regex', 'not_regex',
            'is_empty', 'is_not_empty',
        ];

        $normalized = [];
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $dimension = isset($filter['dimension']) ? (string) $filter['dimension'] : '';
            $operator = isset($filter['operator']) ? (string) $filter['operator'] : 'equals';
            $value = isset($filter['value']) ? (string) $filter['value'] : '';

            if ($dimension === '') {
                continue;
            }
            if (!in_array($dimension, $availableDimensions, true)) {
                throw new \Exception('Invalid filter dimension');
            }
            if (!in_array($operator, $allowedOperators, true)) {
                throw new \Exception('Invalid filter operator');
            }
            if (strlen($value) > 1024) {
                throw new \Exception('Filter value must not exceed 1024 characters');
            }
            if (($operator === 'regex' || $operator === 'not_regex') && !self::isSafeRegexPattern($value)) {
                throw new \Exception('Unsafe regex pattern');
            }

            if ($operator === 'is_empty' || $operator === 'is_not_empty') {
                $value = '';
            }

            $normalized[] = [
                'dimension' => $dimension,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        if (empty($normalized)) {
            return null;
        }

        return json_encode($normalized);
    }

    public static function isSafeRegexPattern($pattern)
    {
        if (!is_string($pattern)) {
            return false;
        }
        if ($pattern === '' || strlen($pattern) > self::MAX_REGEX_PATTERN_LENGTH) {
            return false;
        }
        if (strpos($pattern, "\0") !== false) {
            return false;
        }

        // Reject unescaped delimiter — would break preg_match('~...~i') construction.
        // Using ~ as delimiter; reject any unescaped ~ in the pattern.
        if (preg_match('/(?<!\\\\)~/', $pattern)) {
            return false;
        }

        // Reject higher-risk PCRE features and common catastrophic patterns.
        if (preg_match('/\(\?/', $pattern)) {
            return false;
        }
        if (preg_match('/\\\\[1-9]/', $pattern)) {
            return false;
        }
        // Detect quantifier-on-group patterns that can cause catastrophic backtracking.
        if (preg_match('/\([^)]*[+*][^)]*\)[+*{]/', $pattern)) {
            return false;
        }
        if (preg_match('/\(\.\*\)[+*]|\(\.\+\)[+*]/', $pattern)) {
            return false;
        }
        // Detect alternation-inside-repetition: (a|b)+
        if (preg_match('/\([^)]*\|[^)]*\)[+*{]/', $pattern)) {
            return false;
        }

        return true;
    }
}
