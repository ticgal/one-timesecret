<?php

/**
 * -------------------------------------------------------------------------
 * OneTimeSecret plugin for GLPI
 * Copyright (C) 2021-2024 by the TICgal Team.
 * https://www.tic.gal
 * -------------------------------------------------------------------------
 * LICENSE
 * This file is part of the OneTimeSecret plugin.
 * OneTimeSecret plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * OneTimeSecret plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with OneTimeSecret. If not, see
 * <http: //www.gnu.org/licenses />.
 * -------------------------------------------------------------------------
 * @package OneTimeSecret
 * @author the TICgal team
 * @copyright Copyright (c) 2021-2024 TICgal team
 * @license AGPL License 3.0 or (at your option) any later version
 * http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link https://www.tic.gal
 * @since 2021
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;

class PluginOnetimesecretConfig extends CommonDBTM
{
    private static $instance = null;

    public static $rightname = 'config';

    public function __construct()
    {
        global $DB;
        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    /**
     * getTypeName
     *
     * @param  mixed $nb
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return 'One-Time Secret';
    }

    /**
     * getMenuName
     *
     * @return string
     */
    public static function getMenuName(): string
    {
        return 'One-Time Secret';
    }

    /**
     * getInstance
     *
     * @param  mixed $n
     * @return mixed
     */
    public static function getInstance($n = 1): mixed
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            if (!self::$instance->getFromDB($n)) {
                self::$instance->getEmpty();
            }
        }
        return self::$instance;
    }

    /**
     * getLifetimes
     *
     * @return array
     */
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

    /**
     * showConfigForm
     *
     * @return bool
     */
    public static function showConfigForm(): bool
    {
        $config = self::getInstance();

        $lifetimes = self::getLifetimes();

        $template = "@onetimesecret/config.html.twig";
        $template_options = [
            'item'      => $config,
            'lifetimes' => $lifetimes,
            'conn'      => PluginOnetimesecretSecret::authentication()
        ];
        TemplateRenderer::getInstance()->display($template, $template_options);

        return false;
    }

    /**
     * getTabNameForItem
     *
     * @param  mixed $item
     * @param  mixed $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item->getType() == 'Config') {
            return self::getTypeName();
        }
        return '';
    }

    /**
     * displayTabContentForItem
     *
     * @param  mixed $item
     * @param  mixed $tabnum
     * @param  mixed $withtemplate
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() == 'Config') {
            self::showConfigForm($item);
        }
        return true;
    }

    /**
     * prepareInputForUpdate
     *
     * @param  mixed $input
     * @return array
     */
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

    /**
     * install
     *
     * @param  mixed $migration
     * @return void
     */
    public static function install(Migration $migration): void
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
				`server` VARCHAR(255) NOT NULL DEFAULT 'onetimesecret.com',
				`email` VARCHAR(255) NOT NULL DEFAULT '',
				`apikey` VARCHAR(255) NOT NULL DEFAULT '',
				`lifetime` int(11) NOT NULL DEFAULT '24',
				`debug` tinyint(1) NOT NULL default '1',
				`users_id` int {$default_key_sign} NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
			)ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
            COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->query($query) or die($DB->error());

            $users_id = 0;
            $user = new User();
            $a_users = $user->find(['name' => 'Plugin_Onetimesecret']);
            if (count($a_users) == '0') {
                $input = [
                    'name'      => 'Plugin_Onetimesecret',
                    'password'  => mt_rand(30, 39),
                    'firstname' => 'Plugin_Onetimesecret'
                ];
                $users_id = $user->add($input);
            } else {
                $user = current($a_users);
                $users_id = $user['id'];
            }
            $config->add([
                'id'        => 1,
                'users_id'  => $users_id
            ]);
        }
    }
}
