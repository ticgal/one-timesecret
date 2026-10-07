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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Onetimesecret\Config;
use GlpiPlugin\Onetimesecret\Link;
use GlpiPlugin\Onetimesecret\Profile;

define('PLUGIN_ONETIMESECRET_VERSION', '4.0.0-beta.1');
define('PLUGIN_ONETIMESECRET_MIN_GLPI', '12.0.0');
define('PLUGIN_ONETIMESECRET_MAX_GLPI', '12.1.0');

/**
 * Init the hooks of the plugins - Needed
 *
 * @return void
 */
function plugin_init_onetimesecret(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    if (Plugin::isPluginActive('onetimesecret')) {
        Plugin::registerClass(Config::class, ['addtabon' => \Config::class]);

        Plugin::registerClass(Profile::class, ['addtabon' => \Profile::class]);

        $PLUGIN_HOOKS['config_page']['onetimesecret'] = 'front/config.form.php';

        $PLUGIN_HOOKS[Hooks::TIMELINE_ANSWER_ACTIONS]['onetimesecret'] = [Link::class, 'timelineAction'];
    }
}

/**
 * Get the name and the version of the plugin - Needed
 *
 * @return array
 */
function plugin_version_onetimesecret(): array
{
    return [
        'name'      => 'OneTimeSecret',
        'version'   => PLUGIN_ONETIMESECRET_VERSION,
        'author'    => '<a href="https://tic.gal">TICGAL</a>',
        'homepage'  => 'https://tic.gal',
        'license'   => 'GPLv3+',
        'requirements' => [
            'glpi'  => [
                'min' => PLUGIN_ONETIMESECRET_MIN_GLPI,
                'max' => PLUGIN_ONETIMESECRET_MAX_GLPI,
            ],
        ],
    ];
}
