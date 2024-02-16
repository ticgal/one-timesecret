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

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isInstalled('onetimesecret') || !$plugin->isActivated('onetimesecret')) {
    Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginOnetimesecretConfig();
if (isset($_POST["update"])) {
    $config->check($_POST['id'], UPDATE);
    $config->update($_POST);
    Html::back();
}

$redirect = $CFG_GLPI["root_doc"] . "/front/config.form.php";
$redirect .= "?forcetab=" . urlencode('PluginOnetimesecretConfig$1');
Html::redirect($redirect);
