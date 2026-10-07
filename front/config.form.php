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

use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Onetimesecret\Config;

if (!Plugin::isPluginActive('onetimesecret')) {
    throw new NotFoundHttpException();
}

Session::checkRight(Config::$rightname, UPDATE);
Config::checkReAuthenticationOrRedirect();

$config = new Config();
if (isset($_POST["update"])) {
    $config->check((int) $_POST['id'], UPDATE);
    $config->update($_POST);
    Html::back();
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Html::redirect($CFG_GLPI["root_doc"] . "/front/config.form.php?forcetab=" . urlencode(Config::class . '$1'));
