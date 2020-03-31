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

	private static $PICTURES_DIR = __DIR__ . "/../pictures/";

	private static function getCommandsConfig($file) {
		$return = array();
		$path = dirname(__FILE__) . "/../config/{$file}";
		$content = file_get_contents($path);
		if (is_json($content)) {
			$return += json_decode($content, true);
		} else {
			log::add(__CLASS__, 'error', __('Fichier de configuration non trouvé:', __FILE__).$path);
		}

		return $return;
	}

	public function createCmdFromDef($commandsDef) {
		$link_cmds = array();
		foreach ($commandsDef as $cmdDef){
			$cmd = $this->getCmd(null, $cmdDef["logicalId"]);
			if (!is_object($cmd)) {
				log::add(__CLASS__, 'debug', 'create:'.$cmdDef["logicalId"].'/'.$cmdDef["name"]);
				$cmd = new myaudiCmd();
				$cmd->setLogicalId($cmdDef["logicalId"]);
				$cmd->setEqLogic_id($this->getId());
				$cmd->setName(__($cmdDef["name"], __FILE__));
				if(isset($cmdDef["isHistorized"])) {
					$cmd->setIsHistorized($cmdDef["isHistorized"]);
				}
				if(isset($cmdDef["isVisible"])) {
					$cmd->setIsVisible($cmdDef["isVisible"]);
				}
				if (isset($cmdDef['template'])) {
					foreach ($cmdDef['template'] as $key => $value) {
						$cmd->setTemplate($key, $value);
					}
				}
			}
			$cmd->setType($cmdDef["type"]);
			$cmd->setSubType($cmdDef["subtype"]);
			if(isset($cmdDef["generic_type"])) {
				$cmd->setGeneric_type($cmdDef["generic_type"]);
			}
			if (isset($cmdDef['display'])) {
				foreach ($cmdDef['display'] as $key => $value) {
					if ($key=='title_placeholder' || $key=='message_placeholder') {
						$value = __($value, __FILE__);
					}
					$cmd->setDisplay($key, $value);
				}
			}
			if(isset($cmdDef["unite"])) {
				$cmd->setUnite($cmdDef["unite"]);
			}

			if (isset($cmdDef['configuration'])) {
				foreach ($cmdDef['configuration'] as $key => $value) {
					$cmd->setConfiguration($key, $value);
				}
			}

			if (isset($cmdDef['value'])) {
				$link_cmds[$cmdDef["logicalId"]] = $cmdDef['value'];
			}

			$cmd->save();

			if (isset($cmdDef['initialValue'])) {
				$cmdValue = $cmd->execCmd();
				if ($cmdValue=='') {
					$this->checkAndUpdateCmd($cmdDef["logicalId"], $cmdDef['initialValue']);
				}
			}
		}

		foreach ($link_cmds as $cmd_logicalId => $link_logicalId) {
			$cmd = $this->getCmd(null, $cmd_logicalId);
			$linkCmd = $this->getCmd(null, $link_logicalId);

			if (is_object($cmd) && is_object($linkCmd)) {
				$cmd->setValue($linkCmd->getId());
				$cmd->save();
			}
		}
	}

	public static function dependancy_install() {
		log::remove(__CLASS__.'_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependency', 'log' => log::getPathToLog(__CLASS__.'_update'));
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__.'_update');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependency')) {
			$return['state'] = 'in_progress';
		} else {
            if (exec('python3 -c \'import pkgutil; print(1 if pkgutil.find_loader("requests") else 0)\'') == 0) {
                $return['state'] = 'nok';
			} else {
				$return['state'] = 'ok';
			}
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
        if ($user=='') {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Le nom d\'utilisateur n\'est pas configuré', __FILE__);

        } elseif ($pswd=='') {
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

		$path = realpath(dirname(__FILE__) . '/../../resources/myaudid');
		$cmd = 'sudo python3 ' . $path . '/myaudid.py';
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

	public static function cron() {
		foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
			if ($eqLogic->getIsEnable() != 1) continue;
			$autorefresh = $eqLogic->getConfiguration('autorefresh', '');
			if ($autorefresh == '')  continue;
			try {
				$cron = new Cron\CronExpression($autorefresh, new Cron\FieldFactory);
				if ($cron->isDue()) {
					$eqLogic->refresh();
				}
			} catch (Exception $e) {
				log::add(__CLASS__, 'error', __('Expression cron non valide pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $autorefresh);
			}
		}
	}

	public static function sendToDaemon($params) {
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] != 'ok') {
			throw new Exception("Le démon n'est pas démarré");
		}
		$params['apikey'] = jeedom::getApiKey(__CLASS__);
		$payLoad = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '55066'));
		socket_write($socket, $payLoad, strlen($payLoad));
		socket_close($socket);
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

		if (!file_exists(myaudi::$PICTURES_DIR)) {
			mkdir(myaudi::$PICTURES_DIR, 0777, true);
		}
		$filepath = myaudi::$PICTURES_DIR.$vehicle['vehicle'].'-'.$vehicle['csid'].'.png';
		if (!file_exists($filepath)) {
			file_put_contents($filepath, file_get_contents($vehicle['data']['Vehicle']['LifeData']['MediaData'][0]['URL']));
		}

		$commands = self::getCommandsConfig('commands.json');
		$eqLogic->createCmdFromDef($commands['vehicle']);
	}

	public function updateVehicleData($data) {
		foreach ($data as $key => $value) {
			switch ($key) {
				case 'TEMPERATURE_OUTSIDE':
					$celcius = ($value/10)-273.15;
					$this->checkAndUpdateCmd($key, $celcius);
					break;
				case 'MAINTENANCE_INTERVAL_DISTANCE_TO_OIL_CHANGE':
				case 'MAINTENANCE_INTERVAL_TIME_TO_OIL_CHANGE':
				case 'MAINTENANCE_INTERVAL_DISTANCE_TO_INSPECTION':
				case 'MAINTENANCE_INTERVAL_TIME_TO_INSPECTION':
					$this->checkAndUpdateCmd($key, $value*-1);
					break;
				default:
					$this->checkAndUpdateCmd($key, $value);
					break;
			}

		}
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
		$file = "{$this->getLogicalId()}-{$this->getConfiguration('csid')}.png";
		log::add(__CLASS__, 'debug', "get image {$file}");
		if (file_exists(myaudi::$PICTURES_DIR.$file)) {
			return "plugins/myaudi/core/pictures/{$file}";
		}
		log::add(__CLASS__, 'debug', "not found?");
		if ($returnPluginIcon) {
			return parent::getImage();
		}
		return '';
	}

	public function refresh() {
		$params = array('method' => 'getVehicleData', 'vin' => $this->getLogicalId());
		myaudi::sendToDaemon($params);
	}
}

class myaudiCmd extends cmd {

	public function execute($_options = array()) {
		$eqlogic = $this->getEqLogic();
		$eqlogic->refresh();
	}
}
