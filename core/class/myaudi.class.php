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

class myaudi extends eqLogic {

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
		return $return;
	}

	public static function deamon_start() {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}

		$path = realpath(dirname(__FILE__) . '/../../resources/myaudid');
		$cmd = 'sudo python3.7 ' . $path . '/myaudid.py';
		$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
		$cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__, '55066');
		$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/myaudi/core/php/jeeMyAudi.php';
		$cmd .= ' --user "' . trim(str_replace('"', '\"', config::byKey('user', __CLASS__))) . '"';
		$cmd .= ' --pswd "' . trim(str_replace('"', '\"', config::byKey('password', __CLASS__))) . '"';
		$cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
		$cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		log::add(__CLASS__, 'info', 'Lancement démon MyAudi');
		$result = exec($cmd . ' >> ' . log::getPathToLog('myaudi_daemon') . ' 2>&1 &');
		$i = 0;
		while ($i < 20) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add(__CLASS__, 'error', __('Impossible de lancer le démon MyAudi, vérifiez le log',__FILE__), 'unableStartDeamon');
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


	public static function createVehicle($vehicle) {
		$eqLogic = eqLogic::byLogicalId($vehicle['vehicle'], __CLASS__);
		if (!is_object($eqLogic)) {
			log::add(__CLASS__, 'info', 'Creating new vehicle with vin="' . $vehicle['vehicle'] . '" and csid="' . $vehicle['csid'] . '"');
			$eqLogic = new self();
			$eqLogic->setLogicalId($vehicle['vehicle']);
			$eqLogic->setConfiguration('csid', $vehicle['csid']);
			$eqLogic->setEqType_name(__CLASS__);
			$eqLogic->setIsEnable(1);
		}
		$eqLogic->setName($vehicle['data']['VehicleSpecification']['ModelCoding']['@name']);
		$eqLogic->save();

		mkdir(__DIR__ . "/../pictures/", 0777, true);
		$filepath = __DIR__ . "/../pictures/{$vehicle['vehicle']}-{$vehicle['csid']}.png";
		file_put_contents($filepath, file_get_contents($vehicle['data']['Vehicle']['LifeData']['MediaData'][0]['URL']));
	}

	public function preInsert() {

	}

	public function postInsert() {

	}

	public function preSave() {

	}

	public function postSave() {

	}

	public function preUpdate() {

	}

	public function postUpdate() {

	}

	public function preRemove() {

	}

	public function postRemove() {

	}

	public function getImage($returnPluginIcon = true) {
		$model = "{$this->getLogicalId()}-{$this->getConfiguration('csid')}";
		log::add(__CLASS__, 'debug', "get image for model {$model}");
		if (file_exists(__DIR__."/../pictures/{$model}.png")) {
			return "plugins/myaudi/core/pictures/{$model}.png";
		}
		log::add(__CLASS__, 'debug', "not found?");
		if ($returnPluginIcon) {
			return parent::getImage();
		}
		return '';
	}
}

class myaudiCmd extends cmd {

	public function preSave() {
		if ($this->getSubtype() == 'message') {
			$this->setDisplay('title_disable', 1);
		}
	}

	private static function sendToDaemon($params) {
		$params['apikey'] = jeedom::getApiKey(__CLASS__);
		$payLoad = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '55066'));
		socket_write($socket, $payLoad, strlen($payLoad));
		socket_close($socket);
	}

	public function execute($_options = array()) {
		$eqlogic = $this->getEqLogic();

		log::add(__CLASS__, 'debug', "sendMessage");
		if ($this->getLogicalId()!='sendMessage') {
			$message = "@{$this->getName()} ";
		}
		$message .= $_options['message'];
		if (isset($_options['answer'])) {
			$message .= "\n".__('Réponses attendues: ', __FILE__) . implode(', ', $_options['answer']);
		}

		if (isset($_options['files']) && is_array($_options['files'])) {
			log::add(__CLASS__, 'debug', "Adding files to message");
			foreach ($_options['files'] as $filepath) {
				$params = array('method' => 'sendFile', 'room' => $eqlogic->getLogicalId(), 'message' => $message, 'description' => $_options['title'], 'file' => $filepath);
				self::sendToDaemon($params);
			}
			return;
		}

		$params = array('method' => 'sendMessage', 'room' => $eqlogic->getLogicalId(), 'message' => $message);
		self::sendToDaemon($params);
	}
}
