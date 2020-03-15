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
try {
    require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

    if (!jeedom::apiAccess(init('apikey'), 'myaudi')) {
        echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
        die();
    }
    if (init('test') != '') {
        echo 'OK';
        log::add('myaudi', 'debug', 'test from daemon');
        die();
    }
    $result = json_decode(file_get_contents("php://input"), true);
    if (!is_array($result)) {
        die();
    }

    if (isset($result['vehicle'])) {
        myaudi::createVehicle($result);
    } elseif (isset($result['vehicleData'])) {
        $eqLogic = eqLogic::byLogicalId($result['vehicleData'], 'myaudi');
        if (!is_object($eqLogic)) {
            log::add('myaudi', 'warning', "Unknown vehicle received {$result['vehicleData']}");
        }
        $eqLogic->updateVehicleData($result);
    } else {
        log::add('myaudi', 'error', 'unknown message received from daemon');
        foreach ($result as $key => $value) {
            log::add('myaudi', 'debug', "{$key}:{$value}");
        }
    }

} catch (Exception $e) {
    log::add('myaudi', 'error', displayException($e));
}
