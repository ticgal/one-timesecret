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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;

global $CFG_GLPI;

$plugin = new Plugin();
if (!$plugin->isInstalled('onetimesecret') || !$plugin->isActivated('onetimesecret')) {
    Html::redirect($CFG_GLPI["root_doc"]);
}

Session::checkRight(PluginOnetimesecretProfile::RIGHT_SEND, READ);

if (!isset($_POST['add'], $_POST['tickets_id'])) {
    throw new BadRequestHttpException();
}

$ticket = new Ticket();
if (!$ticket->getFromDB((int) $_POST['tickets_id'])) {
    throw new NotFoundHttpException();
}
// Same checks as adding a followup from the timeline (entity, ticket visibility, followup rights)
$followup = new ITILFollowup();
$followup_input = ['itemtype' => Ticket::class, 'items_id' => $ticket->getID()];
if (
    !$followup->can(-1, CREATE, $followup_input)
    || !PluginOnetimesecretLink::canSendTo($ticket)
) {
    throw new AccessDeniedHttpException();
}

// GLPI 11 no longer encodes the input: the values are used as typed
$secret     = (string) ($_POST['password'] ?? '');
$passphrase = (string) ($_POST['passphrase'] ?? '');
$lifetime   = $_POST['lifetime'] ?? PluginOnetimesecretConfig::getInstance()->fields['lifetime'];

if ($secret === '') {
    Session::addMessageAfterRedirect(htmlescape(__("Secret is missing", "onetimesecret")), false, ERROR);
} elseif (!PluginOnetimesecretConfig::isValidLifetime($lifetime)) {
    throw new BadRequestHttpException();
} else {
    $link = PluginOnetimesecretSecret::createSecret($secret, (int) $lifetime, $passphrase);
    if ($link !== false) {
        PluginOnetimesecretSecret::addFollowup($ticket, $link, (int) $lifetime, $passphrase);
    } else {
        Session::addMessageAfterRedirect(htmlescape(__('Something wrong happened', 'onetimesecret')), false, ERROR);
        $config = PluginOnetimesecretConfig::getInstance();
        if ($config->fields['apiuser'] == '' || $config->fields['apikey'] == '') {
            $href = $CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode(PluginOnetimesecretConfig::class . '$1');
            Session::addMessageAfterRedirect(
                '<a href="' . htmlescape($href) . '">' . htmlescape(__('Please, check the configuration', 'onetimesecret')) . '</a>',
                false,
                ERROR
            );
        }
    }
}

Html::back();
