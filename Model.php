<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CustomDataFeedExport;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;

class Model
{
    public static $rawPrefix = 'datafeed';
    private $table;

    public function __construct()
    {
        $this->table = Common::prefixTable(self::$rawPrefix);
    }

    public function getAllFeeds($idSite = null, $login = null)
    {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE deleted = 0';
        $bind = [];

        if ($idSite !== null) {
            $query .= ' AND idsite = ?';
            $bind[] = $idSite;
        }

        if ($login !== null) {
            $query .= ' AND login = ?';
            $bind[] = $login;
        }

        $query .= ' ORDER BY name ASC';

        return Db::fetchAll($query, $bind);
    }

    public function getFeed($idFeed)
    {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE idfeed = ? AND deleted = 0';
        return Db::fetchRow($query, [$idFeed]);
    }

    public function createFeed($feed)
    {
        unset($feed['idfeed']); // let AUTO_INCREMENT assign the ID
        $feed['ts_created'] = date('Y-m-d H:i:s');
        if (empty($feed['token'])) {
            $feed['token'] = bin2hex(random_bytes(32));
        }

        $db = $this->getDb();
        $db->insert($this->table, $feed);

        return (int) $db->lastInsertId();
    }

    public function updateFeed($idFeed, $feed)
    {
        $idFeed = (int) $idFeed;
        $this->getDb()->update($this->table, $feed, "idfeed = " . $idFeed);
    }

    public function deleteFeed($idFeed)
    {
        $idFeed = (int) $idFeed;
        $this->getDb()->update($this->table, ['deleted' => 1], "idfeed = " . $idFeed);
    }

    public function deleteUserFeedsForSite($userLogin, $idSite)
    {
        $query = 'UPDATE ' . $this->table . ' SET deleted = 1 WHERE login = ? AND idsite = ?';
        Db::query($query, [$userLogin, $idSite]);
    }

    private function getDb()
    {
        return Db::get();
    }

    public static function install()
    {
        $feedTable = "`idfeed` INT(11) NOT NULL AUTO_INCREMENT,
                      `idsite` INTEGER(11) NOT NULL,
                      `login` VARCHAR(100) NOT NULL,
                      `token` VARCHAR(64) NOT NULL DEFAULT '',
                      `name` VARCHAR(255) NOT NULL,
                      `description` VARCHAR(500) NULL,
                      `dimensions` TEXT NOT NULL,
                      `filters` TEXT NULL,
                      `ts_created` TIMESTAMP NULL,
                      `deleted` TINYINT(4) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`idfeed`),
                      UNIQUE KEY `token` (`token`)";

        DbHelper::createTable(self::$rawPrefix, $feedTable);

        // Add columns if they don't exist (for upgrades)
        self::addFiltersColumnIfMissing();
        self::addTokenColumnIfMissing();
    }

    public static function addFiltersColumnIfMissing()
    {
        $table = Common::prefixTable(self::$rawPrefix);
        try {
            $columns = Db::fetchAll("SHOW COLUMNS FROM " . $table . " LIKE 'filters'");
            if (empty($columns)) {
                Db::exec("ALTER TABLE " . $table . " ADD COLUMN `filters` TEXT NULL AFTER `dimensions`");
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    public static function addTokenColumnIfMissing()
    {
        $table = Common::prefixTable(self::$rawPrefix);
        try {
            $columns = Db::fetchAll("SHOW COLUMNS FROM " . $table . " LIKE 'token'");
            if (empty($columns)) {
                Db::exec("ALTER TABLE " . $table . " ADD COLUMN `token` VARCHAR(64) NOT NULL DEFAULT '' AFTER `login`");
                // Add the unique constraint (best-effort; ignore if it already exists)
                try {
                    Db::exec("ALTER TABLE " . $table . " ADD UNIQUE KEY `token` (`token`)");
                } catch (\Exception $e) {
                    // Key may already exist on some upgrade paths
                }
                // Backfill tokens for any existing rows
                $rows = Db::fetchAll("SELECT idfeed FROM " . $table . " WHERE token = ''");
                foreach ($rows as $row) {
                    Db::query(
                        "UPDATE " . $table . " SET token = ? WHERE idfeed = ?",
                        [bin2hex(random_bytes(32)), $row['idfeed']]
                    );
                }
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    public static function uninstall()
    {
        Db::dropTables(Common::prefixTable(self::$rawPrefix));
    }
}
