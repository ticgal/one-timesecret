<?php

/**
 * -------------------------------------------------------------------------
 * OneTimeSecret plugin for GLPI
 * Copyright (C) 2021-2024 by the TICgal Team.
 * https://www.tic.gal
 * -------------------------------------------------------------------------
 * LICENSE
 * This file is part of the OneTimeSecret plugin.
 * OneTimeSecret plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * OneTimeSecret plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with OneTimeSecret. If not, see
 * <http: //www.gnu.org/licenses />.
 * -------------------------------------------------------------------------
 * @package OneTimeSecret
 * @author the TICgal team
 * @copyright Copyright (c) 2021-2024 TICgal team
 * @license AGPL License 3.0 or (at your option) any later version
 * http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link https://www.tic.gal
 * @since 2021
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginOnetimesecretProfile extends Profile
{
    public static $rightname = "profile";

    /**
     * getTabNameForItem
     *
     * @param  mixed $item
     * @param  mixed $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        switch ($item->getType()) {
            case 'Profile':
                return self::createTabEntry("One-Time Secret");
        }
        return '';
    }

    /**
     * displayTabContentForItem
     *
     * @param  mixed $item
     * @param  mixed $tabnum
     * @param  mixed $withtemplate
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        switch ($item->getType()) {
            case 'Profile':
                $profile = new self();
                $profile->showForm($item->getID());
                break;
        }
        return true;
    }

    /**
     * showForm
     *
     * @param  mixed $profiles_id
     * @param  mixed $openform
     * @param  mixed $closeform
     * @return bool
     */
    public function showForm($profiles_id = 0, $openform = true, $closeform = true): bool
    {
        $profile = new Profile();
        $profile->getFromDB($profiles_id);
        echo "<div class='firstbloc'>";
        if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) && $openform) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
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

    /**
     * getAllRights
     *
     * @return array
     */
    public function getAllRights(): array
    {
        $a_rights = [];
        $a_rights = array_merge($a_rights, $this->getRightsGeneral());
        return $a_rights;
    }

    /**
     * getRightsGeneral
     *
     * @return array
     */
    public function getRightsGeneral(): array
    {
        $rights = [
            [
                'rights'    => [READ => __('Read')],
                'label'     => __('Display OneTimeSecret button', 'onetimesecret'),
                'field'     => 'plugin_onetimesecret_send'
            ]
        ];
        return $rights;
    }

    /**
     * addDefaultProfileInfos
     *
     * @param  mixed $profiles_id
     * @param  mixed $rights
     * @return void
     */
    public static function addDefaultProfileInfos($profiles_id, $rights): void
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (
                !countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id, 'name' => $right]
                )
            ) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    /**
     * createFirstAccess
     *
     * @param  mixed $profiles_id
     * @return void
     */
    public static function createFirstAccess($profiles_id): void
    {
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            self::addDefaultProfileInfos($profiles_id, [$right['field'] => ALLSTANDARDRIGHT]);
        }
    }

    /**
     * removeRightsFromSession
     *
     * @return void
     */
    public static function removeRightsFromSession(): void
    {
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    /**
     * initProfile
     *
     * @return void
     */
    public static function initProfile(): void
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
                if (
                    is_array($info) &&
                    ((!empty($info['itemtype'])) || (!empty($info['rights']))) &&
                    (!empty($info['label'])) && (!empty($info['field']))
                ) {
                    if (isset($info['rights'])) {
                        $rights = $info['rights'];
                    } else {
                        $rights = $profile->getRightsFor($info['itemtype']);
                    }
                    foreach ($rights as $right => $label) {
                        $dataprofile['_' . $info['field']][$right] = 1;
                        $_SESSION['glpiactiveprofile'][$data['field']] = $right;
                    }
                }
            }
            $profile->update($dataprofile);
        }
    }

    /**
     * install
     *
     * @param  mixed $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        $migration->displayMessage("Init profiles");
        self::initProfile();
    }

    /**
     * uninstall
     *
     * @param  mixed $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        $pfProfile = new self();
        $a_rights = $pfProfile->getAllRights();
        $migration->displayMessage("Delete profiles");
        foreach ($a_rights as $data) {
            ProfileRight::deleteProfileRights([$data['field']]);
        }
    }
}
