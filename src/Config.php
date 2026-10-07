<?php

/*
-------------------------------------------------------------------------
OneTimeSecret plugin for GLPI
Copyright (C) 2021-2026 by the TICGAL Team.
https://www.tic.gal
-------------------------------------------------------------------------
LICENSE
This file is part of the OneTimeSecret plugin.
OneTimeSecret plugin is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.
OneTimeSecret plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with OneTimeSecret. If not, see
<http: //www.gnu.org/licenses />.
--------------------------------------------------------------------------
@package OneTimeSecret
@author the TICGAL team
@copyright Copyright (C) 2021 - 2026 TICGAL team
@license AGPL License 3.0 or (at your option) any later version
http://www.gnu.org/licenses/agpl-3.0-standalone.html
@link https://www.tic.gal
@since 2021
----------------------------------------------------------------------
*/

namespace GlpiPlugin\Onetimesecret;

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use GLPIKey;
use Migration;
use Session;

class Config extends CommonDBTM
{
    public static string $rightname = 'config';

    private static ?self $_instance = null;

    public function __construct()
    {
        /** @var \DBmysql $DB */
        global $DB;
        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return false;
    }

    protected static function itemTypeRequiresReauthentication(): bool
    {
        return true;
    }

    public static function getTypeName($nb = 0): string
    {
        return 'One-Time Secret';
    }

    public static function getMenuName(): string
    {
        return 'One-Time Secret';
    }

    public static function getIcon(): string
    {
        return 'ti ti-lock';
    }

    public static function getInstance(): self
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

    public static function getLifetimes(): array
    {
        $one_day_in_sec = 86400;
        $one_hour_in_sec = 3600;
        $one_minute_in_sec = 60;

        $lifetimes = [];

        $lifetimes[$one_day_in_sec * 30] = sprintf(_n('%d day', '%d days', 30), 30);
        $lifetimes[$one_day_in_sec * 14] = sprintf(_n('%d day', '%d days', 14), 14);
        $lifetimes[$one_day_in_sec * 7] = sprintf(_n('%d day', '%d days', 7), 7);
        $lifetimes[$one_day_in_sec * 3] = sprintf(_n('%d day', '%d days', 3), 3);
        $lifetimes[$one_day_in_sec] = sprintf(_n('%d day', '%d days', 1), 1);
        $lifetimes[$one_hour_in_sec * 12] = sprintf(_n('%d hour', '%d hours', 12), 12);
        $lifetimes[$one_hour_in_sec * 4] = sprintf(_n('%d hour', '%d hours', 4), 4);
        $lifetimes[$one_hour_in_sec] = sprintf(_n('%d hour', '%d hours', 1), 1);
        $lifetimes[$one_minute_in_sec * 30] = sprintf(_n('%d minute', '%d minutes', 30), 30);
        $lifetimes[$one_minute_in_sec * 5] = sprintf(_n('%d minute', '%d minutes', 5), 5);

        return $lifetimes;
    }

    public static function isValidLifetime(mixed $lifetime): bool
    {
        return is_numeric($lifetime) && array_key_exists((int) $lifetime, self::getLifetimes());
    }

    /**
     * Host name of a One-Time Secret server, with an optional port (no scheme, no path)
     */
    public static function isValidServer(string $server): bool
    {
        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$/i', $server) === 1;
    }

    public static function showConfigForm(): bool
    {
        $config = self::getInstance();

        $has_apikey = !empty($config->fields['apikey']);

        $config->fields['apikey'] = '';

        TemplateRenderer::getInstance()->display('@onetimesecret/config.html.twig', [
            'item'       => $config,
            'params'     => ['candel' => false],
            'lifetimes'  => self::getLifetimes(),
            'has_apikey' => $has_apikey,
        ]);

        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof \Config) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof \Config) {
            return self::showConfigForm();
        }
        return false;
    }

    public function prepareInputForUpdate($input): array|false
    {
        if (isset($input['server'])) {
            $input['server'] = trim($input['server']);
            if (!self::isValidServer($input['server'])) {
                Session::addMessageAfterRedirect(
                    htmlescape(__('Invalid server: use a host name like eu.onetimesecret.com', 'onetimesecret')),
                    false,
                    ERROR,
                );
                return false;
            }
        }
        if (isset($input['lifetime']) && !self::isValidLifetime($input['lifetime'])) {
            unset($input['lifetime']);
        }
        if (isset($input['apikey'])) {
            if (!empty($input['apikey'])) {
                $input['apikey'] = (new GLPIKey())->encrypt($input["apikey"]);
            } else {
                unset($input['apikey']);
            }
        }
        if (isset($input['_blank_apikey'])) {
            $input['apikey'] = '';
        }
        return $input;
    }

    public static function install(Migration $migration): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $default_charset    = DBConnection::getDefaultCharset();
        $default_collation  = DBConnection::getDefaultCollation();
        $default_key_sign   = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        $config = new self();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
				`id` int {$default_key_sign} NOT NULL auto_increment,
				`server` VARCHAR(255) NOT NULL DEFAULT 'eu.onetimesecret.com',
				`email` VARCHAR(255) NOT NULL DEFAULT '',
                `apiuser` VARCHAR(255) NOT NULL DEFAULT '',
				`apikey` VARCHAR(255) NOT NULL DEFAULT '',
				`lifetime` int(11) NOT NULL DEFAULT '86400',
				`debug` tinyint(1) NOT NULL default '1',
				PRIMARY KEY (`id`)
			)ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            // Insert default config after table creation (lifetime expressed in seconds, API v2)
            $config->add([
                'id'       => 1,
                'lifetime' => 86400,
            ]);
        } else {
            $migration->changeField($table, 'server', 'server', 'VARCHAR(250)', ['value' => 'eu.onetimesecret.com']);
            // Since 3.3.0
            $migration->addField($table, 'apiuser', 'string');
            $migration->migrationOneTable($table);

            // Prior to the API v2 rewrite, 'lifetime' was stored in hours. Since 3.1.0 it is
            // used directly as seconds (see Secret::hoursToSeconds), so any leftover value
            // still in the old hours scale must be converted once on upgrade.
            $legacy_lifetime = (int) ($config->fields['lifetime'] ?? 0);
            if ($legacy_lifetime > 0 && $legacy_lifetime <= 744 && !self::isValidLifetime($legacy_lifetime)) {
                $migration->displayMessage("Converting legacy lifetime value ($legacy_lifetime hours) to seconds");
                $DB->update($table, ['lifetime' => $legacy_lifetime * HOUR_TIMESTAMP], ['id' => 1]);
            }
        }

        return true;
    }

    public static function uninstall(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;
        $table = self::getTable();

        if ($DB->tableExists($table)) {
            $migration->displayMessage("Dropping table $table");
            $migration->dropTable($table);
        }
    }
}
