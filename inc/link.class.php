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

if(!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;

class PluginOnetimesecretLink extends CommonDBTM
{
    public static $rightname = 'followup';

    public function getItilObjectItemType()
    {
        return str_replace('One-Time Secret', '', $this->getType());
    }

    public static function getTypeName($nb=0)
    {
        return __('One-Time Secret', 'onetimesecret');
    }

    public static function timelineAction($params=[])
    {
        global $DB;

        $item = $params['item'];
        $config = PluginOnetimesecretConfig::getInstance();

        switch ($item::getType()) {
            case Ticket::getType():
                $req = $DB->request(
                    'glpi_profilerights',
                    [
                        'profiles_id'   => $_SESSION['glpiactiveprofile']["id"],
                        'name'          => 'plugin_onetimesecret_send'
                    ]
                );
                foreach ($req as $right) {
                    if ($item->getField('status') < Ticket::SOLVED && $right["rights"] == 1) {
                        $obj = new self();
                        $timeline["PluginOnetimesecretLink_" . 1] = [
                            'type'      => PluginOnetimesecretLink::class,
                            'item'      => $obj,
                            'itiltype'  => 'PluginOnetimesecretLink',
                            'icon'      => "fa-solid fa-s px-1",
                            'label'     => self::getTypeName()
                        ];

                        $color = 'DD4A22';
                        $style = <<<CSS
                            .action-PluginOnetimesecretLink_1, .action-PluginOnetimesecretLink_1:hover {
                                background-color: #$color;
                                color: white;
                            }
                        CSS;

                        echo "<style>$style</style>";

                        return $timeline;
                    }
                }
                break;
        }
        return [];
    }

    public function showForm($ID, array $params=[])
    {
        $config = PluginOnetimesecretConfig::getInstance();

        $rand = mt_rand();
        $item = $params['parent'];
        $entity = $item->getEntityID();

        $lifetimes = PluginOnetimesecretConfig::getLifetimes();

        $template = "@onetimesecret/link.html.twig";
        $template_options = [
            'item'              => $item,
            'entity'            => $entity,
            'action'            => Toolbox::getItemTypeFormURL(self::getType()),
            'rand'              => $rand,
            'possible_values'   => $lifetimes,
            'lifetime'          => $config->fields["lifetime"]
        ];
        TemplateRenderer::getInstance()->display($template, $template_options);
    }

    public function getEmpty()
    {
        return true;
    }

    public static function install(Migration $migration)
    {
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

            $DB->query($query) or die($DB->error());
        }
    }
}
