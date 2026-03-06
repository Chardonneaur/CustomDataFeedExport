<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CustomDataFeedExport;

use Piwik\Plugin;

class CustomDataFeedExport extends Plugin
{
    public function install()
    {
        Model::install();
    }

    public function activate()
    {
        Model::addFiltersColumnIfMissing();
        Model::addTokenColumnIfMissing();
    }

    public function uninstall()
    {
        Model::uninstall();
    }
}
