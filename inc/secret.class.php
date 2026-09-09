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

if (!defined("GLPI_ROOT")) {
    echo "Sorry. You can't access directly to this file";
    return;
}

class PluginOnetimesecretSecret extends CommonDBTM
{
    public static function createSecret($params = []): bool|string
    {
        global $CFG_GLPI;

        $config = PluginOnetimesecretConfig::getInstance();
        $apikey = (new GLPIKey())->decrypt($config->fields["apikey"]);
        $curl = curl_init();

        $body = [
            'secret' => [
                'kind'   => 'conceal',
                'secret' => html_entity_decode($params["password"], ENT_QUOTES | ENT_HTML5),
                'ttl'    => self::hoursToSeconds($params["lifetime"]),
            ]
        ];

        if ($config->fields['server'] !== 'onetimesecret.com') {
            $body['secret']['share_domain'] = $config->fields['server'];
        }

        if ($params["passphrase"] != "") {
            $body['secret']['passphrase'] = html_entity_decode($params["passphrase"], ENT_QUOTES | ENT_HTML5);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://' . $config->fields['server'] . '/api/v2/secret/conceal',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($config->fields["apiuser"] . ":" . $apikey)
            ],
        ]);

        if (!empty($CFG_GLPI["proxy_name"])) {
            curl_setopt($curl, CURLOPT_PROXY, $CFG_GLPI["proxy_name"]);
        }
        if (!empty($CFG_GLPI["proxy_user"])) {
            $proxy_creds      = !empty($CFG_GLPI["proxy_user"])
                ? $CFG_GLPI["proxy_user"] . ":" . (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])
                : "";
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_creds);
        }

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);

        if (isset($data["record"]["secret"]["identifier"]) && $data["record"]["secret"]["identifier"] != "") {
            return "https://" . $config->fields['server'] . "/secret/" . $data["record"]["secret"]["identifier"];
        } else {
            return false;
        }
    }

    public static function hoursToSeconds(int $hours): int
    {
        // Cap must stay >= the largest option returned by PluginOnetimesecretConfig::getLifetimes()
        return min((int)$hours, 2592000);
    }

    public static function addFollowup(array $params, $text = ''): bool
    {
        global $DB, $CFG_GLPI;

        $query = [
            'FROM' => Ticket::getTable(),
            'WHERE' => [
                'id' => $params["tickets_id"]
            ]
        ];

        foreach ($DB->request($query) as $ticket) {
            if ($ticket['status'] < CommonITILObject::SOLVED) {
                $link = new PluginOnetimesecretLink();
                $link_input = [
                    'secret'     => $text,
                    'ttl'        => $params["lifetime"],
                    'passphrase' => (isset($params["passphrase"]) ? $params["passphrase"] : '')
                ];
                $link->add($link_input);

                $fup = new ITILFollowup();

                $content = __('Hi,', 'onetimesecret') . "<br><br>" . __('As mentioned in our previous conversation, this message is meant to share sensitive information with you.', 'onetimesecret') . "<br><br>";
                $content .= __('A secret link <b>only works once</b> and <b>then disappears forever</b>. Do not open it if you are not the intended recipient.', 'onetimesecret') . "<br><br><br><br>";
                $content .= __('Here you have', 'onetimesecret') . " ";
                $content .= "<a href='" . $text . "' target='_blank'>" . __('your secret link', 'onetimesecret') . "</a>." . "<br><br><br><br>";

                if ($params["passphrase"] != "") {
                    $content .= __('I will send you the required passphrase to open it using an alternative method for security reasons.', 'onetimesecret') . "<br><br>";
                }
                $content .= __('Bear in mind:', 'onetimesecret') . "<br><ul><li>" . __("A secret link can only be opened once and will expire afterwards.", 'onetimesecret') . "</li>";
                $content .= "<li>" . sprintf(__('This secret link will expire %1$s after its generation.', 'onetimesecret'), Html::timestampToString($params["lifetime"], false)) . "</li></ul>";
                $content .= "<br>" . __("Regards,", 'onetimesecret');

                //Switch to the desired language
                $bak_language = $_SESSION["glpilanguage"];

                $query = [
                    'FROM' => Ticket_User::getTable(),
                    'WHERE' => [
                        'tickets_id' => $params["tickets_id"],
                        'type' => 1
                    ]
                ];

                // Defaults used when the ticket has no requester left (deleted/unassigned)
                $lang = $CFG_GLPI["language"];
                $input = [
                    'items_id'  => $params["tickets_id"],
                    'itemtype'  => Ticket::getType(),
                    'content'   => $content,
                    'users_id'  => Session::getLoginUserID()
                ];

                foreach ($DB->request($query) as $ticket_user) {
                    $user = new User();
                    $user->getFromDB($ticket_user["users_id"]);
                    $lang = $user->fields["language"];
                    if ($lang == null) {
                        $lang = $CFG_GLPI["language"];
                    }

                    if (Session::getLoginUserID() == $ticket_user["users_id"]) {
                        $input['_status'] = CommonITILObject::ASSIGNED;
                    }
                }

                Session::loadLanguage($lang);
                $_SESSION["glpilanguage"] = $lang;

                $fup->add($input);

                // Restore default language
                $_SESSION["glpilanguage"] = $bak_language;
                Session::loadLanguage();
            }
        }

        return true;
    }
}
