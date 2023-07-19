<?php
/*
 -------------------------------------------------------------------------
OneTimeSecret plugin for GLPI
 Copyright (C) 2021-2022 by the TICgal Team.
 https://www.tic.gal/
 -------------------------------------------------------------------------
 LICENSE
 This file is part of theOneTimeSecret plugin.
OneTimeSecret plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.
OneTimeSecret plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along withOneTimeSecret. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package  OneTimeSecret
 @author    the TICgal team
 @copyright Copyright (c) 2021-2022 TICgal team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://www.tic.gal/
 @since     2021-2022
 ----------------------------------------------------------------------
*/

if(!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginOnetimesecretProfile extends Profile
{
    public static $rightname="config";

    public function getTabNameForItem(CommonGLPI $item, $withtemplate=0)
    {
        switch ($item->getType()) {
            case 'Profile':
                return self::createTabEntry("One-Time Secret");
                break;
        }
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case 'Profile':
                $profile = new self();
                $profile->showForm($item->getID());
                break;
        }
        return true;
    }

    public function showForm($profiles_id = 0, $openform = true, $closeform = true)
    {
        $profile = new Profile();
        $profile->getFromDB($profiles_id);
        echo "<div class='firstbloc'>";
        if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) && $openform) {
            echo "<form method='post' action='".$profile->getFormURL()."'>";
        }

        $rights = $this->getRightsGeneral();
        $profile->displayRightsChoiceMatrix(
            $rights,
            [
                'canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __('General'),
            ]
        );
        if ($canedit && $closeform) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";

        $this->showLegend();
        return true;
    }

    public function getAllRights()
    {
        $a_rights = [];
        $a_rights = array_merge($a_rights, $this->getRightsGeneral());
        return $a_rights;
    }

    public function getRightsGeneral()
    {
        $rights = [
            [
                'rights' 	=> [READ => __('Read')],
                'label' 	=> __('Display OneTimeSecret button', 'onetimesecret'),
                'field' 	=> 'plugin_onetimesecret_send'
            ]
        ];
        return $rights;
    }

    public static function addDefaultProfileInfos($profiles_id, $rights)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (!countElementsInTable('glpi_profilerights', ['profiles_id' => $profiles_id, 'name' => $right])) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public static function createFirstAccess($profiles_id)
    {
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            self::addDefaultProfileInfos($profiles_id, [$right['field'] => ALLSTANDARDRIGHT]);
        }
    }

    public static function removeRightsFromSession()
    {
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    public static function initProfile()
    {
        $pfProfile = new self();
        $profile   = new Profile();
        $a_rights  = $pfProfile->getAllRights();
        foreach ($a_rights as $data) {
            if (countElementsInTable("glpi_profilerights", ['name' => $data['field']]) == 0) {
                ProfileRight::addProfileRights([$data['field']]);
                $_SESSION['glpiactiveprofile'][$data['field']] = 0;
            }
        }

        if (isset($_SESSION['glpiactiveprofile'])) {
            $dataprofile       = [];
            $dataprofile['id'] = $_SESSION['glpiactiveprofile']['id'];
            $profile->getFromDB($_SESSION['glpiactiveprofile']['id']);
            foreach ($a_rights as $info) {
                if (is_array($info) && ((!empty($info['itemtype'])) || (!empty($info['rights']))) && (!empty($info['label'])) && (!empty($info['field']))) {
                    if (isset($info['rights'])) {
                        $rights = $info['rights'];
                    } else {
                        $rights = $profile->getRightsFor($info['itemtype']);
                    }
                    foreach ($rights as $right => $label) {
                        $dataprofile['_'.$info['field']][$right] = 1;
                        $_SESSION['glpiactiveprofile'][$data['field']] = $right;
                    }
                }
            }
            $profile->update($dataprofile);
        }
    }

    public static function install(Migration $migration)
    {
        self::initProfile();
    }

    public static function uninstall()
    {
        $pfProfile = new self();
        $a_rights = $pfProfile->getAllRights();
        foreach ($a_rights as $data) {
            ProfileRight::deleteProfileRights([$data['field']]);
        }
    }
}
