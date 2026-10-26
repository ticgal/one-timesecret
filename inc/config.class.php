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

if (!defined('GLPI_ROOT')) {
    echo "Sorry. You can't access directly to this file";
    return;
}

use Glpi\Application\View\TemplateRenderer;

class PluginOnetimesecretConfig extends CommonDBTM
{
    private static $_instance = null;

    /** Never returned by the REST API, even encrypted */
    public static $undisclosedFields = ['apikey'];

    public function __construct()
    {
        global $DB;
        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function getTypeName($nb = 0): string
    {
        return 'One-Time Secret';
    }

    public static function getMenuName(): string
    {
        return 'One-Time Secret';
    }

    public static function getInstance(): self
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

    public static function getLifetimes(): array
    {
        $one_day_in_sec = 86400;
        $one_hour_in_sec = 3600;
        $one_minute_in_sec = 60;

        $lifetimes = [];

        $lifetimes[$one_day_in_sec * 30] = sprintf(_n('%d day', '%d days', 30), 30);
        $lifetimes[$one_day_in_sec * 14] = sprintf(_n('%d day', '%d days', 14), 14);
        $lifetimes[$one_day_in_sec * 7] = sprintf(_n('%d day', '%d days', 7), 7);
        $lifetimes[$one_day_in_sec * 3] = sprintf(_n('%d day', '%d days', 3), 3);
        $lifetimes[$one_day_in_sec] = sprintf(_n('%d day', '%d days', 1), 1);
        $lifetimes[$one_hour_in_sec * 12] = sprintf(_n('%d hour', '%d hours', 12), 12);
        $lifetimes[$one_hour_in_sec * 4] = sprintf(_n('%d hour', '%d hours', 4), 4);
        $lifetimes[$one_hour_in_sec] = sprintf(_n('%d hour', '%d hours', 1), 1);
        $lifetimes[$one_minute_in_sec * 30] = sprintf(_n('%d minute', '%d minutes', 30), 30);
        $lifetimes[$one_minute_in_sec * 5] = sprintf(_n('%d minute', '%d minutes', 5), 5);

        return $lifetimes;
    }

    public static function isValidLifetime(mixed $lifetime): bool
    {
        return is_numeric($lifetime) && array_key_exists((int) $lifetime, self::getLifetimes());
    }

    /**
     * Host name of a One-Time Secret server, with an optional port (no scheme, no path)
     */
    public static function isValidServer(string $server, bool $allow_internal = false): bool
    {
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$/iD', $server) !== 1) {
            return false;
        }

        if ($allow_internal) {
            return true;
        }

        // Public hosts only: the API key is sent there
        $host = strtolower(explode(':', $server)[0]);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }
        if (!str_contains($host, '.') || preg_match('/\.(local|localhost|internal|lan|home|corp)$/', $host) === 1) {
            return false;
        }
        // A numeric or hexadecimal last label (127.1, 2130706433, 0x7f.0.0.1) is parsed as an IP address by curl
        return preg_match('/\.(0x[0-9a-f]*|[0-9]+)$/', $host) !== 1;
    }

    /**
     * Is this IP address routable on the public Internet? IPv6 forms that embed an IPv4 address
     * (IPv4-mapped, NAT64, 6to4, IPv4-compatible) are judged by the IPv4 address they carry.
     */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $packed = inet_pton($ip);

        if (strlen($packed) === 4) {
            foreach ([
                '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
                '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
                '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3',
            ] as $range) {
                if (self::ipInRange($ip, $range)) {
                    return false;
                }
            }
            return true;
        }

        if (self::ipInRange($ip, '::ffff:0:0/96') || self::ipInRange($ip, '64:ff9b::/96') || self::ipInRange($ip, '::/96')) {
            return self::isPublicIp((string) inet_ntop(substr($packed, 12, 4)));
        }
        if (self::ipInRange($ip, '2002::/16')) {
            return self::isPublicIp((string) inet_ntop(substr($packed, 2, 4)));
        }
        foreach (['fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8', '2001:db8::/32', '100::/64', '64:ff9b:1::/48', '::ffff:0:0:0/96'] as $range) {
            if (self::ipInRange($ip, $range)) {
                return false;
            }
        }
        return true;
    }

    private static function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $a = inet_pton($ip);
        $b = inet_pton($subnet);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bytes = intdiv((int) $bits, 8);
        $rest  = (int) $bits % 8;
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    /**
     * Every address (A, AAAA and the system resolver, which honours /etc/hosts) of a host name
     *
     * @return string[]
     */
    public static function resolveIps(string $host): array
    {
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? '';
        }
        if ($ips === []) {
            $ips = @gethostbynamel($host) ?: [];
        }
        return array_values(array_unique(array_filter($ips)));
    }

    /**
     * Addresses of a host name, or an empty array when it has none or any of them is private/reserved
     *
     * @return string[]
     */
    public static function resolvePublicIps(string $host): array
    {
        $ips = self::resolveIps($host);
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return [];
            }
        }
        return $ips;
    }

    /**
     * Is an API key stored and still readable? (an empty key re-encrypted by a GLPI key rotation is not)
     */
    public static function hasApiKey(): bool
    {
        $config = self::getInstance();
        return !empty($config->fields['apikey']) && (string) (new GLPIKey())->decrypt($config->fields['apikey']) !== '';
    }

    public static function showConfigForm(): false
    {
        $config = self::getInstance();

        $has_apikey = self::hasApiKey();

        $config->fields['apikey'] = '';

        $lifetimes = self::getLifetimes();

        $template = "@onetimesecret/config.html.twig";
        $template_options = [
            'item'      => $config,
            'lifetimes' => $lifetimes,
            'has_apikey' => $has_apikey
        ];
        TemplateRenderer::getInstance()->display($template, $template_options);

        return false;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item->getType() == 'Config') {
            return self::createTabEntry("One-Time Secret", 0, null, 'ti ti-user-check');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() == 'Config') {
            self::showConfigForm();
        }
        return true;
    }

    public function prepareInputForAdd($input): array|false
    {
        return $this->prepareInputForUpdate($input);
    }

    public function prepareInputForUpdate($input): array|false
    {
        if (isset($input['server'])) {
            $input['server'] = trim($input['server']);
            $allow_internal = (bool) ($input['allow_internal'] ?? $this->fields['allow_internal'] ?? false);
            if (!self::isValidServer($input['server'], $allow_internal)) {
                Session::addMessageAfterRedirect(
                    htmlescape(__('Invalid server: use a host name like eu.onetimesecret.com', 'onetimesecret')),
                    false,
                    ERROR
                );
                return false;
            }
        }
        // The stored API key must not be sent to a different server: ask for it again
        if (
            isset($input['server'], $this->fields['server'])
            && $input['server'] !== $this->fields['server']
            && empty($input['apikey'])
        ) {
            $input['apikey'] = '';
            $input['_blank_apikey'] = 1;
            Session::addMessageAfterRedirect(
                htmlescape(__('The server changed: enter the API key again', 'onetimesecret')),
                false,
                WARNING
            );
        }
        if (isset($input['lifetime']) && !self::isValidLifetime($input['lifetime'])) {
            unset($input['lifetime']);
        }
        if (isset($input['apikey'])) {
            if (!empty($input['apikey'])) {
                $input['apikey'] = (new GLPIKey())->encrypt($input["apikey"]);
            } else {
                unset($input['apikey']);
            }
        }
        if (isset($input['_blank_apikey'])) {
            $input['apikey'] = '';
        }
        return $input;
    }

    public static function install(Migration $migration): bool
    {
        global $DB;

        $default_charset    = DBConnection::getDefaultCharset();
        $default_collation  = DBConnection::getDefaultCollation();
        $default_key_sign   = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        $config = new self();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
				`id` int {$default_key_sign} NOT NULL auto_increment,
				`server` VARCHAR(255) NOT NULL DEFAULT 'eu.onetimesecret.com',
				`email` VARCHAR(255) NOT NULL DEFAULT '',
                `apiuser` VARCHAR(255) NOT NULL DEFAULT '',
				`apikey` VARCHAR(255) NOT NULL DEFAULT '',
				`allow_internal` tinyint(1) NOT NULL DEFAULT '0',
				`lifetime` int(11) NOT NULL DEFAULT '86400',
				`debug` tinyint(1) NOT NULL default '1',
				PRIMARY KEY (`id`)
			)ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            // Insert default config after table creation (lifetime expressed in seconds, API v2)
            $config->add([
                'id'       => 1,
                'lifetime' => 86400
            ]);
        } else {
            $migration->changeField($table, 'server', 'server', 'VARCHAR(250)', ['value' => 'eu.onetimesecret.com']);
            // Since 3.3.0
            $migration->addField($table, 'apiuser', 'string');
            // Since 3.3.1
            $had_allow_internal = $DB->fieldExists($table, 'allow_internal');
            $migration->addField($table, 'allow_internal', 'bool', ['value' => 0]);
            $migration->migrationOneTable($table);

            // Since 3.3.1 the server must be a bare host name: drop a scheme or a trailing slash
            // saved by older versions (both used to work once concatenated into the API URL)
            $server = (string) ($config->fields['server'] ?? '');
            $allow_internal_saved = (bool) ($config->fields['allow_internal'] ?? false);
            $normalized = rtrim(preg_replace('~^https?://~i', '', trim($server)), '/');
            if ($normalized !== $server && self::isValidServer($normalized, true)) {
                $migration->displayMessage("Normalizing server '$server' to '$normalized'");
                $DB->update($table, ['server' => $normalized], ['id' => 1]);
                $server = $normalized;
            }

            // A server already saved on an internal network keeps working: either it fails the strict
            // validation or its name resolves (DNS, /etc/hosts) to a non-public address. Only when upgrading
            // from a version without the option: later upgrades must not change what the admin chose.
            if ($server !== '' && !$had_allow_internal && !$allow_internal_saved) {
                $internal = !self::isValidServer($server);
                if (!$internal) {
                    foreach (self::resolveIps(explode(':', $server)[0]) as $ip) {
                        $internal = $internal || !self::isPublicIp($ip);
                    }
                }
                if ($internal) {
                    $migration->displayMessage("Server '$server' is internal: enabling 'Server on an internal network'");
                    $DB->update($table, ['allow_internal' => 1], ['id' => 1]);
                }
            }

            // Prior to the API v2 rewrite, 'lifetime' was stored in hours. Since 3.1.0 it is
            // used directly as seconds (see PluginOnetimesecretSecret::capLifetime), so any
            // leftover value still in the old hours scale must be converted once on upgrade.
            $legacy_lifetime = (int) $config->fields['lifetime'];
            if ($legacy_lifetime > 0 && $legacy_lifetime <= 744 && !in_array($legacy_lifetime, array_keys(self::getLifetimes()), true)) {
                $migration->displayMessage("Converting legacy lifetime value ($legacy_lifetime hours) to seconds");
                $DB->update($table, ['lifetime' => $legacy_lifetime * HOUR_TIMESTAMP], ['id' => 1]);
            }
        }

        return true;
    }

    public static function uninstall(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;
        $tableConfig = self::getTable();

        if ($DB->tableExists($tableConfig)) {
            $migration->displayMessage("Dropping table $tableConfig");
            $DB->doQuery("DROP TABLE `$tableConfig`;");
        }
    }
}
