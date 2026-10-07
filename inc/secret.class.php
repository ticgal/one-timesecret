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

class PluginOnetimesecretSecret
{
    /** The One-Time Secret API answers with a small JSON document */
    private const MAX_RESPONSE_SIZE = 1048576;

    /**
     * Create the secret on the One-Time Secret server
     *
     * @param string $secret
     * @param int $lifetime Lifetime in seconds
     * @param string $passphrase
     *
     * @return string|false URL of the secret link, false on error
     */
    public static function createSecret(string $secret, int $lifetime, string $passphrase = ''): string|false
    {
        global $CFG_GLPI;

        $config = PluginOnetimesecretConfig::getInstance();
        $server = (string) $config->fields['server'];
        if (!PluginOnetimesecretConfig::isValidServer($server)) {
            Toolbox::logInFile('onetimesecret', sprintf("Invalid server '%s'\n", $server));
            return false;
        }
        $apikey = (string) (new GLPIKey())->decrypt($config->fields["apikey"]);

        $body = [
            'secret' => [
                'kind'   => 'conceal',
                'secret' => $secret,
                'ttl'    => self::hoursToSeconds($lifetime),
            ]
        ];

        if ($server !== 'onetimesecret.com') {
            $body['secret']['share_domain'] = $server;
        }

        if ($passphrase !== '') {
            $body['secret']['passphrase'] = $passphrase;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://' . $server . '/api/v2/secret/conceal',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            // No redirects: a 307/308 would send the secret again to another host
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXFILESIZE    => self::MAX_RESPONSE_SIZE,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($config->fields["apiuser"] . ":" . $apikey)
            ],
        ]);

        if (!empty($CFG_GLPI["proxy_name"])) {
            curl_setopt($curl, CURLOPT_PROXY, $CFG_GLPI["proxy_name"]);
            curl_setopt($curl, CURLOPT_PROXYPORT, (int) $CFG_GLPI["proxy_port"]);
        }
        if (!empty($CFG_GLPI["proxy_user"])) {
            $proxy_creds = $CFG_GLPI["proxy_user"] . ":" . (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_creds);
        }

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $httpcode !== 200 || strlen($response) > self::MAX_RESPONSE_SIZE) {
            Toolbox::logInFile(
                'onetimesecret',
                sprintf("Unable to create the secret on %s: HTTP %d %s\n", $server, $httpcode, $error)
            );
            return false;
        }

        $data = json_decode($response, true);
        $identifier = $data['record']['secret']['identifier'] ?? '';
        if (!is_string($identifier) || preg_match('/^[a-z0-9]+$/i', $identifier) !== 1) {
            Toolbox::logInFile('onetimesecret', sprintf("Unexpected response from %s\n", $server));
            return false;
        }

        return 'https://' . $server . '/secret/' . $identifier;
    }

    public static function hoursToSeconds(int $hours): int
    {
        // Cap must stay >= the largest option returned by PluginOnetimesecretConfig::getLifetimes()
        return min($hours, 2592000);
    }

    /**
     * Add the followup with the secret link to the ticket, in the language of its requester
     *
     * @param Ticket $ticket
     * @param string $link URL of the secret link
     * @param int $lifetime Lifetime in seconds
     * @param string $passphrase
     *
     * @return bool
     */
    public static function addFollowup(Ticket $ticket, string $link, int $lifetime, string $passphrase = ''): bool
    {
        global $CFG_GLPI;

        if ($ticket->fields['status'] >= CommonITILObject::SOLVED) {
            return false;
        }

        // The passphrase is not stored: it must be sent to the requester by another channel
        (new PluginOnetimesecretLink())->add([
            'secret' => $link,
            'ttl'    => $lifetime,
        ]);

        // The ticket status is left to core (reopen rules of ITILFollowup)
        $input = [
            'items_id'  => $ticket->getID(),
            'itemtype'  => Ticket::getType(),
            'users_id'  => Session::getLoginUserID(),
        ];

        // Defaults used when the ticket has no requester left (deleted/unassigned)
        $lang = $CFG_GLPI["language"];
        $ticket_users = (new Ticket_User())->find([
            'tickets_id' => $ticket->getID(),
            'type'       => CommonITILActor::REQUESTER,
        ]);
        foreach ($ticket_users as $ticket_user) {
            $user = new User();
            if ($user->getFromDB($ticket_user["users_id"]) && !empty($user->fields["language"])) {
                $lang = $user->fields["language"];
            }
        }

        // Switch to the requester language to write the followup
        $bak_language = $_SESSION["glpilanguage"];
        $_SESSION["glpilanguage"] = $lang;
        Session::loadLanguage($lang);

        try {
            $input['content'] = self::getFollowupContent($link, $lifetime, $passphrase !== '');
        } finally {
            // Restore the user language
            $_SESSION["glpilanguage"] = $bak_language;
            Session::loadLanguage();
        }

        return (new ITILFollowup())->add($input) !== false;
    }

    private static function getFollowupContent(string $link, int $lifetime, bool $has_passphrase): string
    {
        // Translations are escaped; the only markup kept from a translation is a bare <b>
        $bold = static fn(string $text): string => str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], htmlescape($text));

        $content = htmlescape(__('Hi,', 'onetimesecret')) . "<br><br>";
        $content .= htmlescape(__('As mentioned in our previous conversation, this message is meant to share sensitive information with you.', 'onetimesecret')) . "<br><br>";
        $content .= $bold(__('A secret link <b>only works once</b> and <b>then disappears forever</b>. Do not open it if you are not the intended recipient.', 'onetimesecret')) . "<br><br><br><br>";
        $content .= htmlescape(__('Here you have', 'onetimesecret')) . " ";
        $content .= "<a href='" . htmlescape($link) . "' target='_blank'>" . htmlescape(__('your secret link', 'onetimesecret')) . "</a>." . "<br><br><br><br>";

        if ($has_passphrase) {
            $content .= htmlescape(__('I will send you the required passphrase to open it using an alternative method for security reasons.', 'onetimesecret')) . "<br><br>";
        }
        $content .= htmlescape(__('Bear in mind:', 'onetimesecret')) . "<br><ul><li>" . htmlescape(__("A secret link can only be opened once and will expire afterwards.", 'onetimesecret')) . "</li>";
        $content .= "<li>" . htmlescape(sprintf(__('This secret link will expire %1$s after its generation.', 'onetimesecret'), Html::timestampToString($lifetime, false))) . "</li></ul>";
        $content .= "<br>" . htmlescape(__("Regards,", 'onetimesecret'));

        return $content;
    }
}
