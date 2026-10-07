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

namespace GlpiPlugin\Onetimesecret;

use CommonDBTM;
use CommonITILObject;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Migration;
use Session;
use Ticket;

/**
 * Secret links sent to tickets.
 *
 * Only written by the plugin: the table is not reachable through the generic GLPI list and form pages.
 */
class Link extends CommonDBTM
{
    public static string $rightname = 'followup';

    /** Key of the answer action in the ticket timeline (also used in the action CSS class) */
    private const TIMELINE_ACTION = 'PluginOnetimesecretLink_1';

    public static function getTypeName($nb = 0): string
    {
        return __('One-Time Secret', 'onetimesecret');
    }

    public static function getIcon(): string
    {
        return 'ti ti-lock';
    }

    public static function canView(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return false;
    }

    /**
     * Can the current user send a secret link to this ticket?
     */
    public static function canSendTo(Ticket $ticket): bool
    {
        return Session::haveRight(Profile::RIGHT_SEND, READ)
            && !$ticket->isNewItem()
            && $ticket->fields['status'] < CommonITILObject::SOLVED
            && $ticket->canAddFollowups();
    }

    /**
     * Hook::TIMELINE_ANSWER_ACTIONS
     */
    public static function timelineAction(array $params = []): array
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof Ticket || !self::canSendTo($item)) {
            return [];
        }

        $config = Config::getInstance();
        if (empty($config->fields['apiuser']) || empty($config->fields['apikey'])) {
            return [];
        }

        echo "<style>
            .action-" . self::TIMELINE_ACTION . ", .action-" . self::TIMELINE_ACTION . ":hover {
                background-color: #DD4A22;
                color: white;
            }
        </style>";

        return [
            self::TIMELINE_ACTION => [
                'type'          => self::class,
                'class'         => self::class,
                'item'          => new self(),
                'icon'          => self::getIcon(),
                'label'         => self::getTypeName(),
                'short_label'   => self::getTypeName(),
            ],
        ];
    }

    public function showForm($ID, array $options = []): bool
    {
        $item = $options['parent'] ?? null;
        if (!$item instanceof Ticket) {
            return false;
        }

        TemplateRenderer::getInstance()->display('@onetimesecret/link.html.twig', [
            'item'            => $item,
            'action'          => self::getFormURL(),
            'rand'            => mt_rand(),
            'possible_values' => Config::getLifetimes(),
            'lifetime'        => Config::getInstance()->fields['lifetime'],
        ]);

        return true;
    }

    public static function install(Migration $migration): bool
    {
        /** @var \DBmysql $DB */
        global $DB;
        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
                `id` int {$default_key_sign} NOT NULL auto_increment,
                `secret` VARCHAR(255) NOT NULL DEFAULT '',
                `ttl` int(11) NOT NULL DEFAULT '24',
                `passphrase` VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`)
            )ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
        return true;
    }

    public static function uninstall(Migration $migration): void
    {
        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);
    }
}
