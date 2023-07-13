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
use Glpi\Toolbox\Sanitizer;

if(!defined("GLPI_ROOT")) {
    die("Sorry. You can't access directly to this file");
}


class PluginOnetimesecretSecret extends CommonDBTM
{
    public static function authentication()
    {
        global $CFG_GLPI;

        $config = PluginOnetimesecretConfig::getInstance();
        $apikey = (new GLPIKey())->decrypt($config->fields["apikey"]);
        $curl = curl_init();
        $server = "https://".$config->fields["email"] . ":" . $apikey."@".$config->fields["server"]."/api";

        curl_setopt($curl, CURLOPT_URL, $server);
        if (!empty($CFG_GLPI["proxy_name"])) {
            curl_setopt($curl, CURLOPT_PROXY, $CFG_GLPI["proxy_name"]);
        }
        if(!empty($CFG_GLPI["proxy_user"])) {
            $proxy_creds      = !empty($CFG_GLPI["proxy_user"])
            ? $CFG_GLPI["proxy_user"] . ":" . (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])
            : "";
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_creds);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $headers = array();
        $headers[] = 'Authorization: Basic ' . base64_encode($config->fields["email"].':'.$apikey) ."\r\n";
        $headers[] = "Content-Type: text/html; charset=utf-8\r\n";
        $headers[] = "Content-type: application/x-www-form-urlencoded\r\n";
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            echo "Error:" . curl_error($curl);
        }

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
    }

    public static function createSecret($params = [])
    {
        global $CFG_GLPI;

        $config = PluginOnetimesecretConfig::getInstance();
        $apikey = (new GLPIKey())->decrypt($config->fields["apikey"]);
        $curl = curl_init();

        $post_fields = [
            'secret' 	=> Sanitizer::decodeHtmlSpecialChars($params["password"]),
            'ttl' 		=> self::hoursToSeconds($params["lifetime"])
        ];

        if($params["passphrase"] != "") {
            $post_fields["passphrase"] = Sanitizer::decodeHtmlSpecialChars($params["passphrase"]);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL 			=> 'https://'.$config->fields['server'].'/api/v1/share',
            CURLOPT_RETURNTRANSFER 	=> true,
            CURLOPT_ENCODING 		=> '',
            CURLOPT_MAXREDIRS 		=> 10,
            CURLOPT_TIMEOUT 		=> 0,
            CURLOPT_FOLLOWLOCATION 	=> true,
            CURLOPT_HTTP_VERSION 	=> CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST 	=> 'POST',
            CURLOPT_POSTFIELDS 		=> $post_fields,
            CURLOPT_HTTPHEADER 		=> array(
                "Authorization: Basic " . base64_encode($config->fields["email"] . ":" . $apikey)
            ),
        ));

        if (!empty($CFG_GLPI["proxy_name"])) {
            curl_setopt($curl, CURLOPT_PROXY, $CFG_GLPI["proxy_name"]);
        }
        if(!empty($CFG_GLPI["proxy_user"])) {
            $proxy_creds      = !empty($CFG_GLPI["proxy_user"])
            ? $CFG_GLPI["proxy_user"] . ":" . (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])
            : "";
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_creds);
        }

        $response = curl_exec($curl);

        curl_close($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $data = json_decode($response, true);

        if(isset($data["secret_key"]) && $data["secret_key"] != "") {
            return "https://".$config->fields["server"]."/secret/".$data["secret_key"];
        } else {
            return false;
        }
    }

    public static function hoursToSeconds($hours)
    {
        $minutes = $hours * 60;
        $seconds = $minutes * 60;
        return $seconds;
    }

    public static function addFollowup($params, $text='')
    {
        global $DB, $CFG_GLPI;

        $query = [
            'FROM' => Ticket::getTable(),
            'WHERE' => [
                'id' => $params["tickets_id"]
            ]
        ];

        foreach ($DB->request($query) as $ticket) {

            if ($ticket['status'] < Ticket::SOLVED) {
                $fup = new ITILFollowup();

                $content = __('Hi,', 'onetimesecret')."<br><br>".__('As mentioned in our previous conversation, this message is meant to share sensitive information with you.', 'onetimesecret')."<br><br>";
                $content .= __('A secret link <b>only works once</b> and <b>then disappears forever</b>. Do not open it if you are not the intended recipient.', 'onetimesecret')."<br><br><br><br>";
                $content .= __('Here you have', 'onetimesecret')." ";
                $content .= "<a href='".$text."' target='_blank'>".__('your secret link', 'onetimesecret')."</a>."."<br><br><br><br>";

                if($params["passphrase"]!="") {
                    $content .= __('I will send you the required passphrase to open it using an alternative method for security reasons.', 'onetimesecret')."<br><br>";
                }
                $content .= __('Bear in mind:', 'onetimesecret')."<br><ul><li>".__("A secret link can only be opened once and will expire afterwards.", 'onetimesecret')."</li>";
                $content .= "<li>".sprintf(__('This secret link will expire %1$s after its generation.', 'onetimesecret'), Html::timestampToString($params["lifetime"], false))."</li></ul>";
                $content .= "<br>". __("Regards,", 'onetimesecret');

                //Switch to the desired language
                $bak_language = $_SESSION["glpilanguage"];
                $bak_dropdowntranslations = (isset($_SESSION['glpi_dropdowntranslations']) ? $_SESSION['glpi_dropdowntranslations'] : null);

                $query = [
                    'FROM' => Ticket_User::getTable(),
                    'WHERE' => [
                        'tickets_id' => $params["tickets_id"],
                        'type' => 1
                    ]
                ];
                $input = [];
                foreach ($DB->request($query) as $ticket_user) {
                    $user = new User();
                    $user->getFromDB($ticket_user["users_id"]);
                    $lang = $user->fields["language"];
                    if($lang == null) {
                        $lang = $CFG_GLPI["language"];
                    }

                    if(Session::getLoginUserID() == $ticket_user["users_id"]) {
                        $input = [
                            'items_id' 	=> $params["tickets_id"],
                            'itemtype' 	=> Ticket::getType(),
                            'content' 	=> $content,
                            '_status' 	=> Ticket::ASSIGNED,
                            'users_id' 	=> Session::getLoginUserID()
                        ];
                    } else {
                        $input = [
                            'items_id' 	=> $params["tickets_id"],
                            'itemtype' 	=> Ticket::getType(),
                            'content' 	=> $content,
                            'users_id' 	=> Session::getLoginUserID()
                        ];
                    }
                }

                $_SESSION['glpi_dropdowntranslations'] = DropdownTranslation::getAvailableTranslations($lang);
                Session::loadLanguage($lang);
                $_SESSION["glpilanguage"] = $lang;

                $input = Sanitizer::sanitize($input);
                $fup->add($input);

                // Restore default language
                $_SESSION["glpilanguage"] = $bak_language;
                Session::loadLanguage();
                if ($bak_dropdowntranslations !== null) {
                    $_SESSION['glpi_dropdowntranslations'] = $bak_dropdowntranslations;
                } else {
                    unset($_SESSION['glpi_dropdowntranslations']);
                }
            }
        }

        return true;
    }
}
