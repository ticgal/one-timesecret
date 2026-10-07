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
    die("Sorry. You can't access directly to this file");
}

class PluginOnetimesecretProfile extends CommonGLPI
{
    public static $rightname = 'profile';

    /** Right needed to see the One-Time Secret answer action in tickets */
    public const RIGHT_SEND = 'plugin_onetimesecret_send';

    /**
     * Only a tab of the profiles: there is no list of this itemtype
     * (GLPI would route /plugins/onetimesecret/front/profile.php to its generic list)
     */
    public static function canView(): bool
    {
        return false;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof Profile) {
            return self::createTabEntry("One-Time Secret");
        }
        return '';
    }

    public static function getIcon()
    {
        return "ti ti-user-check";
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof Profile) {
            return (new self())->displayProfileForm($item);
        }
        return false;
    }

    public function displayProfileForm(Profile $profile): bool
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return false;
        }

        // Same right as the core profile form the rights are saved through
        $can_edit = Session::haveRight(self::$rightname, UPDATE);

        echo "<div class='firstbloc'>";
        if ($can_edit) {
            echo "<form method='post' action='" . htmlescape($profile::getFormURL()) . "'>";
        }

        $profile->displayRightsChoiceMatrix(self::getGeneralRights(), [
            'canedit'       => $can_edit,
            'default_class' => 'tab_bg_2',
            'title'         => __('General'),
        ]);

        if ($can_edit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profile->getID()]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";

        return true;
    }

    public static function getGeneralRights(): array
    {
        return [
            [
                'rights'    => [READ => __('Read')],
                'label'     => __('Display OneTimeSecret button', 'onetimesecret'),
                'field'     => self::RIGHT_SEND,
            ],
        ];
    }

    /**
     * Create the plugin rights (without access) for every profile and grant them to the active profile
     */
    public static function install(Migration $migration): void
    {
        foreach (self::getGeneralRights() as $right) {
            if (countElementsInTable(ProfileRight::getTable(), ['name' => $right['field']]) === 0) {
                $migration->displayMessage("Adding profile right " . $right['field']);
                ProfileRight::addProfileRights([$right['field']]);

                if (isset($_SESSION['glpiactiveprofile']['id'])) {
                    $value = array_sum(array_keys($right['rights']));
                    ProfileRight::updateProfileRights($_SESSION['glpiactiveprofile']['id'], [$right['field'] => $value]);
                    $_SESSION['glpiactiveprofile'][$right['field']] = $value;
                }
            }
        }
    }

    public static function uninstall(Migration $migration): void
    {
        foreach (self::getGeneralRights() as $right) {
            $migration->displayMessage("Deleting profile right " . $right['field']);
            ProfileRight::deleteProfileRights([$right['field']]);
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
        }
    }
}
