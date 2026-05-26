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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;

class PluginOnetimesecretConfig extends CommonDBTM
{
    private static $_instance = null;

    public function __construct()
    {
        global $DB;
        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function getTypeName($nb = 0): string
    {
        return 'One-Time Secret';
    }

    public static function getMenuName(): string
    {
        return 'One-Time Secret';
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

    public static function showConfigForm(): false
    {
        $config = self::getInstance();

        $has_apikey = isset($config->fields['apikey']) && !empty($config->fields['apikey']);

        $config->fields['apikey'] = '';

        $lifetimes = self::getLifetimes();

        $template = "@onetimesecret/config.html.twig";
        $template_options = [
            'item'      => $config,
            'lifetimes' => $lifetimes,
            'has_apikey' => $has_apikey
        ];
        TemplateRenderer::getInstance()->display($template, $template_options);

        return false;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item->getType() == 'Config') {
            return self::createTabEntry("One-Time Secret", 0, null, 'ti ti-user-check');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() == 'Config') {
            self::showConfigForm($item);
        }
        return true;
    }

    public function prepareInputForUpdate($input): array
    {
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
				`apikey` VARCHAR(255) NOT NULL DEFAULT '',
				`lifetime` int(11) NOT NULL DEFAULT '24',
				`debug` tinyint(1) NOT NULL default '1',
				PRIMARY KEY (`id`)
			)ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            // Insert default config after table creation
            $config->add([
                'id' => 1
            ]);
        } else {
            $migration->changeField($table, 'server', 'server', 'VARCHAR(250)', ['value' => 'eu.onetimesecret.com']);
            $migration->migrationOneTable($table);
        }
        
        return true;
    }
}