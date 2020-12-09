import logging
import argparse
import sys
import os
import signal
import json
import time

from jeedom.jeedom import jeedom_utils, jeedom_com, jeedom_socket, JEEDOM_SOCKET_MESSAGE

from audiapi import API, Services
from audiapi.model.Vehicle import Vehicle

def read_socket():
    global JEEDOM_SOCKET_MESSAGE
    if not JEEDOM_SOCKET_MESSAGE.empty():
        logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
        message = json.loads(JEEDOM_SOCKET_MESSAGE.get().decode('utf-8'))
        if message['apikey'] != _apikey:
            logging.error("Invalid apikey from socket : " + str(message))
            return
        try:
            if message['method'] == 'getVehicles':
                try:
                    getVehicles()
                except Exception as e:
                    logging.error('getVehicles error : '+str(e))
            elif message['method'] == 'getVehicleData':
                try:
                    api = getAudiAPI()
                    vehicle = Vehicle(vin=message['vin'])
                    getVehicleData(api, vehicle)
                except Exception as e:
                    logging.error('getVehicleData error : '+str(e))

            else:
                logging.error("unknown method:" + str(message['method']))
        except Exception as e:
            logging.error('Send command to demon error : '+str(e))

def listen():
    logging.debug("Start listening")
    jeedomSocket.open()
    try:
        while 1:
            time.sleep(0.01)
            read_socket()
    except KeyboardInterrupt:
        shutdown()

# ----------------------------------------------------------------------------

def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()

def shutdown():
    logging.debug("Shutdown")
    logging.debug("Removing PID file " + str(_pidfile))
    try:
        os.remove(_pidfile)
    except:
        pass
    try:
        jeedomSocket.close()
    except:
        pass
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)

# ----------------------------------------------------------------------------
def getAudiAPI():
    api = API.API()
    logon_service = Services.LogonService(api)
    logging.debug('try restore token...')
    if not logon_service.restore_token():
        logging.debug('login and get new token')
        logon_service.login(_user, _pswd)
    return api

def getVehicles():
    logging.debug('Get vehicles...')
    api = getAudiAPI()
    car_service = Services.CarService(api)
    vehiclesResponse = car_service.get_vehicles()
    for vehicle in vehiclesResponse.vehicles:
        data = car_service.get_vehicle_data(vehicle)
        tmp = {}
        tmp["vehicle"] = vehicle.vin
        tmp["csid"] = vehicle.csid
        tmp["registered"] = vehicle.registered
        tmp["data"] = data.get('getVehicleDataResponse')
        jeedomCom.send_change_immediate(tmp)
        time.sleep(1)
        getVehicleData(api, vehicle)
    del vehiclesResponse
    del car_service
    del api

def getVehicleData(api: API.API, vehicle: Services.Vehicle):
    logging.debug('Get vehicle data...')
    vehicleData = Services.VehicleStatusReportService(api, vehicle).get_stored_vehicle_data()
    tmp = {}
    tmp["vehicleData"] = vehicle.vin
    tmp["data"] = {}
    for data in vehicleData.data_fields:
        tmp["data"][data.name] = data.value

    logging.debug('Get vehicle position...')
    vehiclePosition = Services.CarFinderService(api, vehicle).find()
    tmp["position"] = vehiclePosition["findCarResponse"]["Position"]

    jeedomCom.send_change_immediate(tmp)

    del vehicleData
    del vehiclePosition

    # logging.debug('Get vehicle actions...')
    # action = Services.LockUnlockService(api, vehicle).get_actions()
    # tmp = {}
    # tmp["vehicleAction"] = action
    # jeedomCom.send_change_immediate(tmp)


# ----------------------------------------------------------------------------

_log_level = "error"
_socket_port = 55066
_socket_host = 'localhost'
_pidfile = '/tmp/myaudid.pid'
_apikey = ''
_callback = ''
_cycle = 30

parser = argparse.ArgumentParser(description='Daemon for Jeedom plugin')
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--user", help="username", type=str)
parser.add_argument("--pswd", help="password", type=str)
parser.add_argument("--spin", help="S-PIN", type=str)
parser.add_argument("--socketport", help="Socket Port", type=int)
parser.add_argument("--callback", help="Value to write", type=str)
parser.add_argument("--apikey", help="Value to write", type=str)
parser.add_argument("--pid", help="Value to write", type=str)

parser.add_argument("--cycle", help="Cycle to send event", type=str)
args = parser.parse_args()

_log_level = args.loglevel
_socket_port = args.socketport
_pidfile = args.pid
_apikey = args.apikey
_callback = args.callback
_user = args.user
_pswd = args.pswd
_spin = args.spin

jeedom_utils.set_log_level(_log_level)

logging.info('Start daemon')
logging.info('Log level : '+str(_log_level))
logging.debug('Socket port : '+str(_socket_port))
logging.debug('PID file : '+str(_pidfile))
logging.debug('User : '+str(_user))

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

try:
    jeedom_utils.write_pid(str(_pidfile))
    jeedomSocket = jeedom_socket(port=_socket_port,address=_socket_host)
    jeedomCom = jeedom_com(apikey = _apikey,url = _callback,cycle=_cycle)

    getVehicles()

    listen()
except Exception as e:
    logging.error('Fatal error : '+str(e))

shutdown()