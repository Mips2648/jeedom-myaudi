<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function myaudi_install() {
    $pluginId = 'myaudi';
    config::save('api', config::genKey(), $pluginId);
    config::save("api::{$pluginId}::mode", 'localhost');
    config::save("api::{$pluginId}::restricted", 1);
}

function myaudi_update() {
    $pluginId = 'myaudi';
    config::save('api', config::genKey(), $pluginId);
    config::save("api::{$pluginId}::mode", 'localhost');
    config::save("api::{$pluginId}::restricted", 1);

    unlink(__DIR__ . '/packages.json');

    foreach (eqLogic::byType($pluginId) as $eqLogic) {
        if ($eqLogic->getConfiguration('csid') != '') {
            $eqLogic->remove();
        }
    }

    message::removeAll($pluginId, 'checkDependency');
    $dependencyInfo = myaudi::dependancy_info();
    if (!isset($dependencyInfo['state'])) {
        message::add($pluginId, __('Veuilez vérifier les dépendances', __FILE__), '', 'checkDependency');
    } elseif ($dependencyInfo['state'] == 'nok') {
        try {
            $plugin = plugin::byId($pluginId);
            $plugin->dependancy_install();
        } catch (\Throwable $th) {
            message::add($pluginId, __('Cette mise à jour nécessite de réinstaller les dépendances même si elles sont marquées comme OK', __FILE__));
        }
    }
}

function myaudi_remove() {
    $pluginId = 'myaudi';
    config::remove('api', $pluginId);
    config::remove("api::{$pluginId}::mode");
    config::remove("api::{$pluginId}::restricted");
}
