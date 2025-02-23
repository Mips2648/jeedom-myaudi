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

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../../vendor/autoload.php';

class myaudi extends eqLogic {
	use MipsEqLogicTrait;

	const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';

	public static $_encryptConfigKey = array('user', 'password', 'spin');

	protected static function getSocketPort() {
		return config::byKey('socketport', __CLASS__, 55066);;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => __DIR__ . '/../../resources/install_#stype#.sh', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_update');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
		$return['state'] = 'ok';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependance')) {
			$return['state'] = 'in_progress';
		} elseif (!self::pythonRequirementsInstalled(self::PYTHON_PATH, __DIR__ . '/../../resources/requirements.txt')) {
			log::add(__CLASS__, 'debug', 'pythonRequirementsInstalled nok');
			$return['state'] = 'nok';
		}
		return $return;
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = __CLASS__;
		$return['state'] = 'nok';

		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		$return['launchable'] = 'ok';
		$user = config::byKey('user', __CLASS__);
		$pswd = config::byKey('password', __CLASS__);
		if ($user == '') {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __('Le nom d\'utilisateur n\'est pas configuré', __FILE__);
		} elseif ($pswd == '') {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __('Le mot de passe n\'est pas configuré', __FILE__);
		}
		return $return;
	}

	public static function deamon_start() {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}

		$path = realpath(__DIR__ . '/../../resources/myaudid');
		$cmd = self::PYTHON_PATH . " {$path}/myaudid.py";
		$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
		$cmd .= ' --socketport ' . self::getSocketPort();
		$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/myaudi/core/php/jeeMyAudi.php';
		$cmd .= ' --username "' . trim(str_replace('"', '\"', config::byKey('user', __CLASS__))) . '"';
		$cmd .= ' --password "' . trim(str_replace('"', '\"', config::byKey('password', __CLASS__))) . '"';
		$cmd .= ' --spin "' . trim(str_replace('"', '\"', config::byKey('spin', __CLASS__))) . '"';
		$cmd .= ' --country ' . config::byKey('country', __CLASS__, 'DE');
		$cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
		$cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		log::add(__CLASS__, 'info', 'Lancement démon MyAudi');
		$result = exec($cmd . ' >> ' . log::getPathToLog('myaudi_daemon') . ' 2>&1 &');
		$i = 0;
		while ($i < 10) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 10) {
			log::add(__CLASS__, 'error', __('Impossible de lancer le démon MyAudi, vérifiez le log', __FILE__), 'unableStartDeamon');
			return false;
		}
		message::removeAll(__CLASS__, 'unableStartDeamon');
		return true;
	}

	public static function deamon_stop() {
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		system::kill('myaudid.py');
		// system::fuserk(config::byKey('socketport', __CLASS__));
		sleep(1);
	}

	public static function syncVehicles(array $vehicles) {
		foreach ($vehicles as $vin => $vehicle) {
			/** @var myaudi */
			$eqLogic = eqLogic::byLogicalId($vin, __CLASS__);
			if (!is_object($eqLogic)) {
				log::add(__CLASS__, 'info', "Creating new vehicle with vin='{$vin}'");
				$eqLogic = new self();
				$eqLogic->setLogicalId($vin);
				$eqLogic->setName($vehicle['infos']['media']['short_name']);
				$eqLogic->setEqType_name(__CLASS__);
				$eqLogic->setIsEnable(1);
				$eqLogic->setConfiguration('model_year', $vehicle['infos']['core']['model_year']);
				$eqLogic->setConfiguration('model', $vehicle['infos']['media']['long_name']);
				$eqLogic->save();
			}
			$eqLogic->updateVehicleData($vehicle);
		}
	}

	public function updateVehicleData(array $vehicle) {
		if (isset($vehicle['fuel_status'])) {
			$this->updateFuelStatus($vehicle['fuel_status']);
		}
		if (isset($vehicle['charging'])) {
			$this->updateCharging($vehicle['charging']);
		}
		if (isset($vehicle['vehicle_health_inspection'])) {
			$this->updateVehicleHealthInspection($vehicle['vehicle_health_inspection']);
		}
	}

	private function updateFuelStatus(array $fuelStatus) {
		if (isset($fuelStatus['range_status']['primary_engine'])) {
			$primary_engine = $fuelStatus['range_status']['primary_engine'];
			$this->checkAndUpdateCmd('primary_engine_type', $primary_engine['type']);
			$this->checkAndUpdateCmd('primary_engine_current_soc_pct', $primary_engine['current_soc_pct']);
			$this->checkAndUpdateCmd('primary_engine_remaining_range_km', $primary_engine['remaining_range_km']);
			$this->checkAndUpdateCmd('primary_engine_current_fuel_level_pct', $primary_engine['current_fuel_level_pct']);
		}
		if (isset($fuelStatus['range_status']['secondary_engine'])) {
			$secondary_engine = $fuelStatus['range_status']['secondary_engine'];
			$this->checkAndUpdateCmd('secondary_engine_type', $secondary_engine['type']);
			$this->checkAndUpdateCmd('secondary_engine_current_soc_pct', $secondary_engine['current_soc_pct']);
			$this->checkAndUpdateCmd('secondary_engine_remaining_range_km', $secondary_engine['remaining_range_km']);
			$this->checkAndUpdateCmd('secondary_engine_current_fuel_level_pct', $secondary_engine['current_fuel_level_pct']);
		}
		$this->checkAndUpdateCmd('total_range_km', $fuelStatus['range_status']['total_range_km']);
	}

	private function updateCharging(array $charging) {
		if (isset($charging['charging_status']['charging_state'])) {
			$this->checkAndUpdateCmd('charging_state', $charging['charging_status']['charging_state']);
		}
		if (isset($charging['charging_status']['charge_power_kw'])) {
			$this->checkAndUpdateCmd('charge_power_kw', $charging['charging_status']['charge_power_kw']);
		}
	}

	private function updateVehicleHealthInspection(array $vehicleHealthInspection) {
		if (isset($vehicleHealthInspection['maintenance_status'])) {
			$this->checkAndUpdateCmd('inspection_due_days', $vehicleHealthInspection['maintenance_status']['inspection_due_days']);
			$this->checkAndUpdateCmd('inspection_due_km', $vehicleHealthInspection['maintenance_status']['inspection_due_km']);
			$this->checkAndUpdateCmd('mileage_km', $vehicleHealthInspection['maintenance_status']['mileage_km']);
			$this->checkAndUpdateCmd('oil_service_due_days', $vehicleHealthInspection['maintenance_status']['oil_service_due_days']);
			$this->checkAndUpdateCmd('oil_service_due_km', $vehicleHealthInspection['maintenance_status']['oil_service_due_km']);
		}
	}

	public function postSave() {
		$commands = self::getCommandsFileContent(__DIR__ . '/../config/commands.json');
		// $this->createCommandsFromConfig($commands['vehicle']);
		$this->createCommandsFromConfig($commands['fuel_status']);
		$this->createCommandsFromConfig($commands['vehicle_health_inspection']);
	}
}

class myaudiCmd extends cmd {
}
