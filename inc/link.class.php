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

class PluginOnetimesecretLink extends CommonDBTM {
	public static $rightname='followup';

	public function getItilObjectItemType()
    {
        return str_replace('One-Time Secret', '', $this->getType());
    }

	static function getTypeName($nb=0) {
		return __('One-Time Secret','onetimesecret');
	}


	static function timelineAction($params=[]) {
		global $DB;
		$item=$params['item'];
		$config= new PluginOnetimesecretConfig();
		$config->getFromDB(1);
		switch ($item::getType()) {
			case Ticket::getType():
				$req = $DB->request('glpi_profilerights',
					['profiles_id' => $_SESSION['glpiactiveprofile']["id"],
						'name' => 'plugin_onetimesecret_send'
					]);
				foreach ($req as $right) {
					if ($item->getField('status')<Ticket::SOLVED && $right["rights"] == 1) {						
						$obj=new self();
						$timeline["PluginOnetimesecretLink_" . 1] = [
							'type' => PluginOnetimesecretLink::class,
							'item' => $obj,
							'itiltype' => 'PluginOnetimesecretLink',
							'icon' => "fa-solid fa-s",
							'label' => self::getTypeName()
						];
						return $timeline;
					}
				}
				
				
				break;
		}
		return [];
	}

	function showForm($ID, array $params=[]) {
		$config = new PluginOnetimesecretConfig();
		$config->getFromDB(1);
		$rand = mt_rand();
		$item = $params['parent'];
		$entity = $item->getEntityID();
		
		echo "<div class='firstbloc'>";
		echo "<form name='documentitem_form".$rand."' id='documentitem_form".
				$rand."' method='post' action='".Toolbox::getItemTypeFormURL(self::getType()).
				"' enctype=\"multipart/form-data\">";
		echo "<table class='tab_cadre_fixe'>";
		echo "<tr class='tab_bg_2'><th colspan='5'>".__('Create a password','onetimesecret')."</th></tr>";
		echo "<tr class='tab_bg_1'>";
		
		echo "<td colspan='2'>";
		echo "<input type='hidden' name='entities_id' value='$entity'>";
		echo "<input type='hidden' name='is_recursive' value='".$item->isRecursive()."'>";
		echo "<input type='hidden' name='itemtype' value='".$item->getType()."'>";
		echo "<input type='hidden' name='items_id' value='".$item->getID()."'>";
		echo "<input type='hidden' name='tickets_id' value='".$item->getID()."'>";
		echo "</td>";

		echo "<tr class='tab_bg_1'>";
		echo "<td width='25%'>".__("Password")."</td><td>";
		echo "<input type='password' name='password' id='password' size='40' >";
		echo "</td></tr>\n";


		echo "<td>".__("Password lifetime","onetimesecret")."</td><td>";
		$one_day_in_sec=86400;
		$one_minute_in_sec=60;
		$possible_values = [];

		$possible_values[$one_day_in_sec*7]=sprintf(_n('%d day', '%d days', 7),7);
		$possible_values[$one_day_in_sec*3]=sprintf(_n('%d day', '%d days', 3),3);
		$possible_values[$one_day_in_sec]=sprintf(_n('%d day', '%d days', 1),1);
		$possible_values[($one_minute_in_sec*60)*12]=sprintf(_n('%d hour', '%d hours', 12),12);
		$possible_values[($one_minute_in_sec*60)*4]=sprintf(_n('%d hour', '%d hours', 4),4);
		$possible_values[$one_minute_in_sec*60]=sprintf(_n('%d hour', '%d hours', 1),1);
		$possible_values[$one_minute_in_sec*30]=sprintf(_n('%d minute', '%d minutes', 30),30);
		$possible_values[$one_minute_in_sec*5]=sprintf(_n('%d minute', '%d minutes', 5),5);
		
		Dropdown::showFromArray('lifetime', $possible_values,['value' => $config->fields["lifetime"]]);


		echo "</tr>";

		echo "<tr><th colspan='4'>".__('Optional parameter','onetimesecret')."</th></tr>";

		echo "<tr class='tab_bg_1'>";
		echo "<td width='25%'>".__("Passphrase","onetimesecret")."</td><td>";
		echo "<input type='text' name='passphrase' id='passphrase' size='40' >";
		echo "</td></tr>\n";
		echo "</tr>";

		

		echo "<tr class='tab_bg_1'>";
		echo "<td colspan='2' class='center'>";
		echo "<input type='submit' name='add' value=\""._sx('button', 'Send')."\"class='submit'>";
		echo "</td>";
		echo "</tr>";

		
		echo "</table>";
		Html::closeForm();
		echo "</div>";

	}

	function getEmpty() {
      return true;
   }


   static function install(Migration $migration) {
	global $DB;
	$default_charset = DBConnection::getDefaultCharset();
	$default_collation = DBConnection::getDefaultCollation();
	$default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

	$table = self::getTable();

	if (!$DB->tableExists($table)) {
		$migration->displayMessage("Installing $table");
		$query = "CREATE TABLE IF NOT EXISTS $table (
			`id` int {$default_key_sign} NOT NULL auto_increment,
			`secret` VARCHAR(255)  NOT NULL DEFAULT '',
			`ttl` int(11) NOT NULL DEFAULT '24',
			`passphrase` VARCHAR(255)  NOT NULL DEFAULT '',
			PRIMARY KEY (`id`)
		)ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
		
		$DB->query($query) or die($DB->error());
	}
}
}
