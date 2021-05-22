import logging
import asyncio
from aiohttp import ClientSession

from jeedom.jeedom import jeedom_com
from audiconnect.audi_connect_account import AudiConnectAccount, AudiConnectObserver

class AudiAccount(AudiConnectObserver):

    def __init__(self, _username : str, _password : str, _country : str, _spin : str, jcom : jeedom_com):
        self.username = _username
        self.password = _password
        self.country = _country
        self.spin = _spin
        self.vehicles = set()
        self.loop = asyncio.get_event_loop()
        self.jeedom_com = jcom

    def init_connection(self):
        return self.loop.run_until_complete(self.__async__init_connection())

    def execute_vehicle_action(self, vin, action):
        return self.loop.run_until_complete(self.__async_execute_vehicle_action(vin, action))

    def update(self):
        return self.loop.run_until_complete(self.__async__update())

    def refresh_vehicle_data(self, vin: str):
        return self.loop.run_until_complete(self.__async_refresh_vehicle_data(vin))

    def send_vehicule_to_jeedom(self, vehicle, isUpdate):
        tmp = {}

        if(isUpdate):
            tmp["vehicleData"] = vehicle.vin
        else:
            tmp["vehicle"] = vehicle.vin

        tmp["csid"] = vehicle.csid
        tmp["title"] = vehicle.title
        tmp["model"] = vehicle.model
        tmp["brand"] = vehicle.brand
        tmp["model_year"] = vehicle.model_year
        tmp["type"] = vehicle.type
        tmp["model_family"] = vehicle.model_family
        tmp["model_full"] = vehicle.model_full

        tmp["support_status_report"] = False
        tmp["support_ac"] = False
        tmp["support_position"] = False
        tmp["support_preheater"] = False
        tmp["support_charger"] = False

        if(vehicle.support_status_report):
            tmp["support_status_report"] = True
            tmp["data"] = {}
            for key in vehicle._vehicle.fields:
                tmp["data"][key] = vehicle._vehicle.fields[key]

        if(vehicle.support_climater):
            tmp["support_ac"] = True
            tmp["climatisationState"] = {}
            # tmp["climatisationState"] = vehicle._vehicle.state["climatisationState"]


        if(vehicle.support_position):
            tmp["support_position"] = True
            tmp["position"] = {}
            tmp["position"]['carCoordinate'] = {}
            tmp["position"]['carCoordinate']['latitude'] = vehicle._vehicle.state["position"]["latitude"]
            tmp["position"]['carCoordinate']['longitude'] = vehicle._vehicle.state["position"]["longitude"]

        if(vehicle.support_preheater):
            tmp["support_preheater"] = True
            tmp["preheaterState"] = {}
            # tmp["preheaterState"] = vehicle._vehicle.state["preheaterState"]

        if(vehicle.support_charger):
            tmp["support_charger"] = True
            tmp["chargerState"] = {}
            tmp["chargerState"]["maxChargeCurrent"] = vehicle._vehicle.state["maxChargeCurrent"]
            tmp["chargerState"]["chargingState"] = vehicle._vehicle.state["chargingState"]
            tmp["chargerState"]["actualChargeRate"] = vehicle._vehicle.state["actualChargeRate"]
            tmp["chargerState"]["actualChargeRateUnit"] = vehicle._vehicle.state["chargingPower"]
            tmp["chargerState"]["engineTypeFirstEngine"] = vehicle._vehicle.state["engineTypeFirstEngine"]
            tmp["chargerState"]["engineTypeSecondEngine"] = vehicle._vehicle.state["engineTypeSecondEngine"]
            tmp["chargerState"]["hybridRange"] = vehicle._vehicle.state["hybridRange"]
            tmp["chargerState"]["primaryEngineRange"] = vehicle._vehicle.state["primaryEngineRange"]
            tmp["chargerState"]["secondaryEngineRange"] = vehicle._vehicle.state["secondaryEngineRange"]
            tmp["chargerState"]["stateOfCharge"] = vehicle._vehicle.state["stateOfCharge"]
            tmp["chargerState"]["remainingChargingTime"] = vehicle._vehicle.state["remainingChargingTime"]
            tmp["chargerState"]["plugState"] = vehicle._vehicle.state["plugState"]
            tmp["chargerState"]["chargingState"] = vehicle._vehicle.state["chargingState"]

        self.jeedom_com.send_change_immediate(tmp)

    async def __async__init_connection(self):
        self.clientSession = ClientSession()
        self.connection = AudiConnectAccount(
            session=self.clientSession,
            username=self.username,
            password=self.password,
            country=self.country,
            spin=self.spin,
        )
        self.connection.add_observer(self)

    async def handle_notification(self, vin: str, action: str) -> None:
        logging.debug("Notification received for vin : " + vin + " and action : " + action)
        await self.__async_refresh_vehicle_data(vin)

    async def __async_execute_vehicle_action(self, vin, action):
        if action == "lock":
            await self.connection.set_vehicle_lock(vin, True)
        if action == "unlock":
            await self.connection.set_vehicle_lock(vin, False)
        if action == "start_climatisation":
            await self.connection.set_vehicle_climatisation(vin, True)
        if action == "stop_climatisation":
            await self.connection.set_vehicle_climatisation(vin, False)
        if action == "start_charger":
            await self.connection.set_battery_charger(vin, True)
        if action == "stop_charger":
            await self.connection.set_battery_charger(vin, False)
        if action == "start_preheater":
            await self.connection.set_vehicle_pre_heater(vin, True)
        if action == "stop_preheater":
            await self.connection.set_vehicle_pre_heater(vin, False)
        if action == "start_window_heating":
            await self.connection.set_vehicle_window_heating(vin, True)
        if action == "stop_window_heating":
            await self.connection.set_vehicle_window_heating(vin, False)

    async def __async__update(self):
        try:
            if not await self.connection.update(None):
                return False
        finally:
            logging.debug("End of Update")

        for vehicle in self.connection._vehicles:
            logging.debug("Updating vehicule data for " + vehicle.title + " with vin : " + vehicle.vin)
            self.send_vehicule_to_jeedom(vehicle, False)

    async def __async_refresh_vehicle_data(self, vin: str):
        res = await self.connection.refresh_vehicle_data(vin)

        if res == True:
            await self.connection.update(vin)
            logging.debug("Refresh Vehicule Data Success")
            for vehicle in self.connection._vehicles:
                self.send_vehicule_to_jeedom(vehicle, True)

        else:
            logging.debug("Refresh Vehicule Data Failure")