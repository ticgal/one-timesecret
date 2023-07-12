<?php
/*
 -------------------------------------------------------------------------
OneTimeSecret plugin for GLPI
 Copyright (C) 2021-2022 by the TICgal Team.
 https://www.tic.gal/
 -------------------------------------------------------------------------
 LICENSE
 This file is part of theOneTimeSecret plugin.
OneTimeSecret plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.
OneTimeSecret plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along withOneTimeSecret. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package  OneTimeSecret
 @author    the TICgal team
 @copyright Copyright (c) 2021-2022 TICgal team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://www.tic.gal/
 @since     2021-2022
 ----------------------------------------------------------------------
*/
use Glpi\Plugin\Hooks;

define('PLUGIN_ONETIMESECRET_VERSION', '2.0.3');
define('PLUGIN_ONETIMESECRET_MIN_GLPI', '10.0');
define('PLUGIN_ONETIMESECRET_MAX_GLPI', '11.0');

/**
 * Init the hooks of the plugins - Needed
 *
 * @return void
 */
function plugin_init_onetimesecret()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['onetimesecret'] = true;

    $plugin = new Plugin();
    if ($plugin->isActivated('onetimesecret')) {
        Plugin::registerClass('PluginOnetimesecretConfig', ['addtabon' => 'Config']);

        Plugin::registerClass('PluginOnetimesecretProfile', ['addtabon' => 'Profile']);

        $PLUGIN_HOOKS['config_page']['onetimesecret'] = 'front/config.form.php';

        $PLUGIN_HOOKS[Hooks::TIMELINE_ANSWER_ACTIONS]['onetimesecret'] = [
            'PluginOnetimesecretLink','timelineAction'
        ];
    }
}

/**
 * Get the name and the version of the plugin - Needed
 *
 * @return array
 */
function plugin_version_onetimesecret()
{
    return [
        'name'		=> 'OneTimeSecret',
        'version' 	=> PLUGIN_ONETIMESECRET_VERSION,
        'author' 	=> '<a href="https://tic.gal">TICgal</a>',
        'homepage' 	=> 'https://tic.gal',
        'license' 	=> 'GPLv3+',
        'minGlpiVersion' => PLUGIN_ONETIMESECRET_MIN_GLPI,
        'requirements' => [
            'glpi'  => [
                'min' => PLUGIN_ONETIMESECRET_MIN_GLPI,
                'max' => PLUGIN_ONETIMESECRET_MAX_GLPI,
            ]
        ]
    ];
}

/**
 * Optional : check prerequisites before install : may print errors or add to message after redirect
 *
 * @return boolean
 */
function plugin_onetimesecret_check_prerequisites()
{
    return true;
}

/**
 * Check configuration process for plugin : need to return true if succeeded
 * Can display a message only if failure and $verbose is true
 *
 * @param boolean $verbose Enable verbosity. Default to false
 *
 * @return boolean
 */
function plugin_onetimesecret_check_config($verbose = false)
{
    if (true) { // Your configuration check
        return true;
    }

    if ($verbose) {
        echo "Installed, but not configured";
    }
    return false;
}
