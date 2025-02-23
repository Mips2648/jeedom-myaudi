import asyncio
import logging
import warnings
from aiohttp import ClientSession
from jeedomdaemon.base_daemon import BaseDaemon
from jeedomdaemon.base_config import BaseConfig

from audiconnectpy import AudiConnect, AudiException


class AudiConfig(BaseConfig):
    def __init__(self):
        super().__init__()

        self.add_argument("--username", type=str)
        self.add_argument("--password", type=str)
        self.add_argument("--country", type=str)
        self.add_argument("--spin", type=str)

    @property
    def username(self) -> str:
        return self._args.username

    @property
    def password(self) -> str:
        return self._args.password

    @property
    def country(self) -> str:
        return self._args.country

    @property
    def spin(self) -> str:
        return self._args.spin


class MyAudiDaemon(BaseDaemon):
    def __init__(self):
        self._config = AudiConfig()
        super().__init__(self._config, on_start_cb=self.on_start)

        logging.getLogger('audiconnectpy').setLevel(logging.WARNING)
        warnings.filterwarnings("ignore")

        self._api = None

    async def on_start(self):
        asyncio.create_task(self.__update_task())

    async def __update_task(self):
        try:
            while True:
                async with ClientSession() as session:
                    self._api = AudiConnect(session, self._config.username, self._config.password, self._config.country, self._config.spin)
                    try:
                        await self._api.async_login()
                    except AudiException as error:
                        self._logger.error(error)

                    if self._api.is_connected:
                        self._logger.info("Connected to MyAudi API")
                        try:
                            for vehicle in self._api.vehicles:
                                try:
                                    data = {
                                        "vin": vehicle.vin,
                                        "infos": vehicle.infos,
                                        # "capabilities": vehicle.capabilities,
                                        "fuel_status": vehicle.fuel_status,
                                        # "last_access": vehicle.last_access,
                                        # "position": vehicle.position,
                                        # "location": vehicle.location,
                                        # "access": vehicle.access,
                                        "charging": vehicle.charging,
                                        # "climatisation": vehicle.climatisation,
                                        # "climatisation_timers": vehicle.climatisation_timers,
                                        # "oil_level": vehicle.oil_level,
                                        # "vehicle_lights": vehicle.vehicle_lights,
                                        "vehicle_health_inspection": vehicle.vehicle_health_inspection,
                                        # "measurements": vehicle.measurements,
                                        # "vehicle_health_warnings": vehicle.vehicle_health_warnings
                                    }
                                    await self.add_change(f"vehicle::{vehicle.vin}", data)
                                except Exception as error:
                                    self._logger.error("Impossible to get data for vehicle %s: %s ", vehicle, error)

                                # Lock vehicle if spin
                                # --------------------
                                # await vehicle.async_set_lock(True)

                                # Refresh call remote vehicle
                                # --------------------
                                # await vehicle.async_refresh_vehicle_data()
                                # await vehicle.async_wakeup()

                                # Update Audi API
                                # --------------------
                                # await vehicle.async_update()

                        except AudiException as error:
                            self._logger.error(error)
                        finally:
                            await asyncio.sleep(600)
                    else:
                        self._logger.error("Connection error, retry in 120 seconds")
                        asyncio.sleep(120)
        except asyncio.CancelledError:
            self._logger.info("stop auto update")


MyAudiDaemon().run()
