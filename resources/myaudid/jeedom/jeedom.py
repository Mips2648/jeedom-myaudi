# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
#

import logging
import threading
import _thread as thread
import requests
import collections
import os
from queue import Queue
import socketserver
from socketserver import (TCPServer, StreamRequestHandler)

# ------------------------------------------------------------------------------


class jeedom_com():
    def __init__(self, apikey='', url='', retry=3):
        self.apikey = apikey
        self.url = url
        self.retry = retry
        self.changes = {}
        logging.debug('Init request module v%s', requests.__version__)

    def send_change_immediate(self, change):
        thread.start_new_thread(self.thread_change, (change,))

    def thread_change(self, change):
        logging.debug('Send to jeedom :  %s' % (str(change),))
        i = 0
        while i < self.retry:
            try:
                r = requests.post(self.url + '?apikey=' + self.apikey, json=change, timeout=(0.5, 120), verify=False)
                if r.status_code == requests.codes.ok:
                    break
            except Exception as error:
                logging.error('Error on send request to jeedom ' + str(error)+' retry : '+str(i)+'/'+str(self.retry))
            i = i + 1

    def set_change(self, changes):
        self.changes = changes

    def get_change(self):
        return self.changes

    def merge_dict(self, d1, d2):
        for k, v2 in d2.items():
            v1 = d1.get(k)  # returns None if v1 has no value for this key
            if (isinstance(v1, collections.Mapping) and
                    isinstance(v2, collections.Mapping)):
                self.merge_dict(v1, v2)
            else:
                d1[k] = v2

    def test(self):
        try:
            response = requests.get(self.url + '?apikey=' + self.apikey, verify=False)
            if response.status_code != requests.codes.ok:
                logging.error('Callback error: %s %s. Please check your network configuration page' % (response.status_code, response.text,))
                return False
        except Exception as e:
            logging.error('Callback result as a unknown error: %s. Please check your network configuration page ' % (str(e),))
            return False
        return True

# ------------------------------------------------------------------------------


class jeedom_utils():

    @staticmethod
    def convert_log_level(level='error'):
        LEVELS = {'debug': logging.DEBUG,
                  'info': logging.INFO,
                  'notice': logging.WARNING,
                  'warning': logging.WARNING,
                  'error': logging.ERROR,
                  'critical': logging.CRITICAL,
                  'none': logging.NOTSET}
        return LEVELS.get(level, logging.NOTSET)

    @staticmethod
    def set_log_level(level='error'):
        FORMAT = '[%(asctime)s.%(msecs)03d][%(levelname)s] : %(message)s'
        logging.basicConfig(level=jeedom_utils.convert_log_level(level), format=FORMAT, datefmt='%Y-%m-%d %H:%M:%S')

    @staticmethod
    def stripped(string):
        try:
            return "".join([i for i in string if i in range(32, 127)])
        except Exception as e:
            logging.error('exception on stripped : '+str(e) + ' - ' + str(string))
        return string

    @staticmethod
    def ByteToHex(byteStr):
        return ''.join(["%02X " % ord(x) for x in str(byteStr)]).strip()

    @staticmethod
    def dec2bin(x, width=8):
        return ''.join(str((x >> i) & 1) for i in xrange(width-1, -1, -1))

    @staticmethod
    def dec2hex(dec):
        if dec is None:
            return 0
        return hex(dec)[2:]

    @staticmethod
    def testBit(int_type, offset):
        mask = 1 << offset
        return(int_type & mask)

    @staticmethod
    def clearBit(int_type, offset):
        mask = ~(1 << offset)
        return(int_type & mask)

    @staticmethod
    def split_len(seq, length):
        return [seq[i:i+length] for i in range(0, len(seq), length)]

    @staticmethod
    def write_pid(path):
        pid = str(os.getpid())
        logging.debug("Writing PID " + pid + " to " + str(path))
        open(path, 'w').write("%s\n" % pid)

# ------------------------------------------------------------------------------


JEEDOM_SOCKET_MESSAGE = Queue()


class jeedom_socket_handler(StreamRequestHandler):
    def handle(self):
        global JEEDOM_SOCKET_MESSAGE
        logging.debug("Client connected to [%s:%d]" % self.client_address)
        lg = self.rfile.readline()
        JEEDOM_SOCKET_MESSAGE.put(lg)
        logging.debug("Message read from socket: " + str(lg.strip()))
        self.netAdapterClientConnected = False
        logging.debug("Client disconnected from [%s:%d]" % self.client_address)


class jeedom_socket():

    def __init__(self, address='localhost', port=55000):
        self.address = address
        self.port = port
        socketserver.TCPServer.allow_reuse_address = True

    def open(self):
        self.netAdapter = TCPServer((self.address, self.port), jeedom_socket_handler)
        if self.netAdapter:
            logging.debug("Socket interface started")
            threading.Thread(target=self.loopNetServer, args=()).start()
        else:
            logging.debug("Cannot start socket interface")

    def loopNetServer(self):
        logging.debug("LoopNetServer Thread started")
        logging.debug("Listening on: [%s:%d]" % (self.address, self.port))
        self.netAdapter.serve_forever()
        logging.debug("LoopNetServer Thread stopped")

    def close(self):
        self.netAdapter.shutdown()

    def getMessage(self):
        return self.message

# ------------------------------------------------------------------------------
# END
# ------------------------------------------------------------------------------
