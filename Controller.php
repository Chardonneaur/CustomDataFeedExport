<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CustomDataFeedExport;

use Piwik\Common;
use Piwik\Log;
use Piwik\Piwik;
use Piwik\View;
use Piwik\Plugin\Controller as PluginController;

class Controller extends PluginController
{
    private const MAX_FILTER_LIMIT = 100000;
    private const DEFAULT_FILTER_LIMIT = 1000;

    /**
     * Main page listing all feeds and allowing creation/editing
     */
    public function index()
    {
        Piwik::checkUserHasSomeViewAccess();

        $view = new View('@CustomDataFeedExport/index');
        $this->setGeneralVariablesView($view);

        // Get idSite from the current context
        $idSite = Common::getRequestVar('idSite', $this->idSite ?? 1, 'int');
        $view->idSite = $idSite;

        return $view->render();
    }

    /**
     * Download a feed as CSV
     */
    public function download()
    {
        ob_start();

        try {
            $idFeed = Common::getRequestVar('idFeed', 0, 'int');
            $period = Common::getRequestVar('period', 'day', 'string');
            $date   = Common::getRequestVar('date', 'today', 'string');
            $segment = Common::getRequestVar('segment', false, 'string');
            $filterLimit = Common::getRequestVar('filter_limit', self::DEFAULT_FILTER_LIMIT, 'int');
            if ($filterLimit <= 0) {
                $filterLimit = self::DEFAULT_FILTER_LIMIT;
            }
            $filterLimit = min($filterLimit, self::MAX_FILTER_LIMIT);

            // Whitelist period
            if (!in_array($period, ['day', 'week', 'month', 'year', 'range'], true)) {
                throw new \Exception('Invalid period');
            }

            // Validate date: today|yesterday|YYYY-MM-DD or YYYY-MM-DD,YYYY-MM-DD for range
            if (!preg_match('/^(today|yesterday|\d{4}-\d{2}-\d{2}(,\d{4}-\d{2}-\d{2})?)$/', $date)) {
                throw new \Exception('Invalid date format');
            }

            $api = API::getInstance();
            $model = new Model();
            $feed = $model->getFeed($idFeed);

            if (empty($feed)) {
                throw new \Exception('Feed not found');
            }

            Piwik::checkUserHasViewAccess($feed['idsite']);

            $csv = $api->exportFeed($idFeed, $period, $date, $segment, $filterLimit);

            // Build filename — strip everything except safe chars, including the comma in range dates
            $filename = Common::sanitizeInputValue($feed['name']);
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
            $safeDateSuffix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $date);
            $filename .= '_' . $safeDateSuffix . '.csv';

            // Add UTF-8 BOM for Excel compatibility
            $csv = "\xEF\xBB\xBF" . $csv;

            // Clean ALL output buffers before sending headers
            while (@ob_get_level()) {
                @ob_end_clean();
            }

            // Ensure no previous output
            if (headers_sent($file, $line)) {
                throw new \Exception("Headers already sent in $file on line $line");
            }

            // Send HTTP headers for CSV download
            http_response_code(200);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($csv));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('X-Robots-Tag: noindex, nofollow', true);

            // Output CSV content
            echo $csv;

            // Terminate script cleanly
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit(0);

        } catch (\Throwable $e) {
            while (@ob_get_level()) {
                @ob_end_clean();
            }

            Log::warning('CustomDataFeedExport download error: ' . $e->getMessage());
            throw new \Exception('CSV export failed. Please try again.');
        }
    }
}
