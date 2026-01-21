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
@copyright Copyright (c) 2026 TICGAL team
@license AGPL License 3.0 or (at your option) any later version
http://www.gnu.org/licenses/agpl-3.0-standalone.html
@link https://www.tic.gal
@since 2021
----------------------------------------------------------------------
*/

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isInstalled('onetimesecret') || !$plugin->isActivated('onetimesecret')) {
    Html::redirect($CFG_GLPI["root_doc"]);
}

if (!isset($_POST['password']) || $_POST['password'] == "") {
    Session::addMessageAfterRedirect(__("Secret is missing", "onetimesecret"));
} else {
    PluginOnetimesecretSecret::authentication();
    $_POST['password'] = html_entity_decode($_POST['password'], ENT_QUOTES | ENT_HTML5);
    $_POST['passphrase'] = html_entity_decode($_POST['passphrase'], ENT_QUOTES | ENT_HTML5);
    $link = PluginOnetimesecretSecret::createSecret($_POST);
    if ($link) {
        PluginOnetimesecretSecret::addFollowup($_POST, $link);
    } else {
        Session::addMessageAfterRedirect(__('Something wrong happened', 'onetimesecret'), false, ERROR);
        $config = PluginOnetimesecretConfig::getInstance();
        if ($config->fields['email'] == '' || $config->fields['apikey'] == '') {
            $msg = __('Please, check the configuration', 'onetimesecret');
            $href = "/front/config.form.php?forcetab=PluginOnetimesecretConfig%241";
            Session::addMessageAfterRedirect('<a href="' . $href . '">' . $msg . '</a>', false, ERROR);
        }
    }
}

Html::back();