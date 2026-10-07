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

use GlpiPlugin\Onetimesecret\Config;
use GlpiPlugin\Onetimesecret\Link;
use GlpiPlugin\Onetimesecret\Profile;

/**
 * Classes with an install() / uninstall() method
 *
 * @return array<class-string>
 */
function plugin_onetimesecret_get_classes(): array
{
    return [Config::class, Link::class, Profile::class];
}

function plugin_onetimesecret_install(): bool
{
    $migration = new Migration(PLUGIN_ONETIMESECRET_VERSION);

    foreach (plugin_onetimesecret_get_classes() as $classname) {
        $classname::install($migration);
    }

    $migration->executeMigration();

    return true;
}

function plugin_onetimesecret_uninstall(): bool
{
    $migration = new Migration(PLUGIN_ONETIMESECRET_VERSION);

    foreach (plugin_onetimesecret_get_classes() as $classname) {
        $classname::uninstall($migration);
    }

    $migration->executeMigration();

    return true;
}
