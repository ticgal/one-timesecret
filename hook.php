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
function plugin_onetimesecret_install()
{
    $migration = new Migration(PLUGIN_ONETIMESECRET_VERSION);

    // Parse inc directory
    foreach (glob(dirname(__FILE__).'/inc/*') as $filepath) {
        // Load *.class.php files and get the class name
        if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
            $classname = 'PluginOnetimesecret' . ucfirst($matches[1]);
            include_once($filepath);
            // If the install method exists, load it
            if (method_exists($classname, 'install')) {
                $classname::install($migration);
            }
        }
    }
    $conf = Config::getConfigurationValues('core', ['notifications_push']);
    if (!isset($conf['notifications_push'])) {
        Config::setConfigurationValues('core', ['notifications_push' => 0]);
    }

    return true;
}

function plugin_onetimesecret_uninstall()
{
    $migration = new Migration(PLUGIN_ONETIMESECRET_VERSION);

    // Parse inc directory
    foreach (glob(dirname(__FILE__).'/inc/*') as $filepath) {
        // Load *.class.php files and get the class name
        if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
            $classname = 'PluginOnetimesecret' . ucfirst($matches[1]);
            include_once($filepath);
            // If the uninstall method exists, load it
            if (method_exists($classname, 'uninstall')) {
                $classname::uninstall($migration);
            }
        }
    }

    $config = new Config();
    $config->deleteConfigurationValues(['core', 'notifications_push']);

    return true;
}
