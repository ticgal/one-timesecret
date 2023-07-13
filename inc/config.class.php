<?php
/*
 -------------------------------------------------------------------------
 OneTimeSecret plugin for GLPI
 Copyright (C) 2021-2022 by the TICgal Team.
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
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with OneTimeSecret. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   OneTimeSecret
 @author    the TICgal team
 @copyright Copyright (c) 2021-2022 TICgal team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://www.tic.gal
 @since     2021-2022
 ----------------------------------------------------------------------
*/
if(!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

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

    public static function canCreate()
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canView()
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate()
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function getTypeName($nb=0)
    {
        return __('One-Time Secret', 'onetimesecret');
    }

    public static function getMenuName()
    {
        return __('One-Time Secret', 'onetimesecret');
    }

    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

    public static function showConfigForm()
    {
        $config = self::getInstance();

        $config->showFormHeader(['colspan' => 2, 'formtitle' => __('One-Time Secret', 'onetimesecret')." - ".__('Configuration', 'onetimesecret')]);

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__("Server")."</td><td>";
        echo "<input type='text' name='server' id='server' size='40'  value=\"".$config->fields["server"]."\">";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__("Email")."</td><td>";
        echo "<input type='text' name='email' id='email' size='40' value=\"".$config->fields["email"]."\">";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__("API key", "onetimesecret")."</td><td>";
        echo "<input type='password' name='apikey' id='apikey' size='40' value='".(new GLPIKey())->decrypt($config->fields["apikey"])."'>";
        echo "</td></tr>\n";

        echo "<tr><th colspan='4'>".__('One-Time Secret')." - ".__('Lifetime', 'onetimesecret')."</th></tr>";

        echo "<td>".__("Password lifetime", "onetimesecret")."</td><td>";

        $one_day_in_sec = 86400;
        $one_minute_in_sec = 60;
        $possible_values = [];

        $possible_values[$one_day_in_sec*7] = sprintf(_n('%d day', '%d days', 7), 7);
        $possible_values[$one_day_in_sec*3] = sprintf(_n('%d day', '%d days', 3), 3);
        $possible_values[$one_day_in_sec] = sprintf(_n('%d day', '%d days', 1), 1);
        $possible_values[($one_minute_in_sec*60)*12] = sprintf(_n('%d hour', '%d hours', 12), 12);
        $possible_values[($one_minute_in_sec*60)*4] = sprintf(_n('%d hour', '%d hours', 4), 4);
        $possible_values[$one_minute_in_sec*60] = sprintf(_n('%d hour', '%d hours', 1), 1);
        $possible_values[$one_minute_in_sec*30] = sprintf(_n('%d minute', '%d minutes', 30), 30);
        $possible_values[$one_minute_in_sec*5] = sprintf(_n('%d minute', '%d minutes', 5), 5);

        Dropdown::showFromArray('lifetime', $possible_values, ['value' => $config->fields["lifetime"]]);

        $config->showFormButtons(['candel' => false]);

        return false;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Config') {
            return self::getTypeName();
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'Config') {
            self::showConfigForm($item);
        }
        return true;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset 	= DBConnection::getDefaultCharset();
        $default_collation 	= DBConnection::getDefaultCollation();
        $default_key_sign 	= DBConnection::getDefaultPrimaryKeySignOption();

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
			)ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->query($query) or die($DB->error());

            $users_id = 0;
            $user = new User();
            $a_users = $user->find(['name' => 'Plugin_Onetimesecret']);
            if (count($a_users) == '0') {
                $input = [
                    'name' 		=> 'Plugin_Onetimesecret',
                    'password' 	=> mt_rand(30, 39),
                    'firstname' => 'Plugin_Onetimesecret'
                ];
                $users_id = $user->add($input);
            } else {
                $user = current($a_users);
                $users_id = $user['id'];
            }
            $config->add([
                'id' 		=> 1,
                'users_id'  => $users_id
            ]);
        }
    }
}
