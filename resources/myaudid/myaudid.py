import logging
import argparse
import sys
import os
import signal
import json
import time

from jeedom.jeedom import jeedom_utils, jeedom_com, jeedom_socket, JEEDOM_SOCKET_MESSAGE

from audiconnect.audi_jeedom_account import AudiAccount

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
                    AUDI.update()
                except Exception as e:
                    logging.error('getVehicles error : '+str(e))
            elif message['method'] == 'getVehicleData':
                try:
                    AUDI.refresh_vehicle_data(message['vin'])
                except Exception as e:
                    logging.error('getVehicleData error : '+str(e))
            elif message['method'] == 'lock' or message['method'] == 'unlock' or message['method'] == 'start_climatisation' or message['method'] == 'stop_climatisation' or message['method'] == 'stop_charger' or message['method'] == 'start_charger' or message['method'] == 'start_preheater' or message['method'] == 'stop_preheater' or message['method'] == 'start_window_heating' or message['method'] == 'stop_window_heating':
                try:
                    AUDI.execute_vehicle_action(message['vin'], message['method'])
                except Exception as e:
                    logging.error( message['method'] + ' error : ' + str(e))

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

    AUDI = AudiAccount(_user, _pswd, "DE", _spin, jeedomCom)
    AUDI.init_connection()
    AUDI.update()

    listen()
except Exception as e:
    logging.error('Fatal error : '+str(e))

shutdown()