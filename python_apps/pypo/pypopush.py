import os
import sys
import time
import logging
import logging.config
import pickle
import telnetlib
import calendar
import json
import math
from threading import Thread

from api_clients import api_client
from util import CueFile

from configobj import ConfigObj

# configure logging
logging.config.fileConfig("logging.cfg")

# loading config file
try:
    config = ConfigObj('config.cfg')
    LS_HOST = config['ls_host']
    LS_PORT = config['ls_port']
    PUSH_INTERVAL = 2
except Exception, e:
    logger.error('Error loading config file %s', e)
    sys.exit()

class PypoPush(Thread):
    def __init__(self, q):
        Thread.__init__(self)
        self.api_client = api_client.api_client_factory(config)
        self.cue_file = CueFile()
        self.set_export_source('scheduler')
        self.queue = q

        self.schedule = dict()
        self.playlists = dict()
        self.stream_metadata = dict()

        """
        push_ahead2 MUST be < push_ahead. The difference in these two values
        gives the number of seconds of the window of opportunity for the scheduler
        to catch when a playlist is to be played.
        """
        self.push_ahead = 10
        self.push_ahead2 = self.push_ahead -5

    def set_export_source(self, export_source):
        self.export_source = export_source
        self.cache_dir = config["cache_dir"] + self.export_source + '/'
        self.schedule_tracker_file = self.cache_dir + "schedule_tracker.pickle"
        
    """
    The Push Loop - the push loop periodically checks if there is a playlist 
    that should be scheduled at the current time.
    If yes, the current liquidsoap playlist gets replaced with the corresponding one,
    then liquidsoap is asked (via telnet) to reload and immediately play it.
    """
    def push(self, export_source):
        logger = logging.getLogger('push')

        # get a new schedule from pypo-fetch
        if not self.queue.empty():
            scheduled_data = self.queue.get()
            logger.debug("Received data from pypo-fetch")
            self.schedule = scheduled_data['schedule']
            self.playlists = scheduled_data['playlists']
            self.stream_metadata = scheduled_data['stream_metadata']

        schedule = self.schedule
        playlists = self.playlists
        
        currently_on_air = False
        if schedule:
            playedItems = self.load_schedule_tracker()

            timenow = time.time()
            tcoming = time.localtime(timenow + self.push_ahead)
            str_tcoming_s = "%04d-%02d-%02d-%02d-%02d-%02d" % (tcoming[0], tcoming[1], tcoming[2], tcoming[3], tcoming[4], tcoming[5])

            tcoming2 = time.localtime(timenow + self.push_ahead2)
            str_tcoming2_s = "%04d-%02d-%02d-%02d-%02d-%02d" % (tcoming2[0], tcoming2[1], tcoming2[2], tcoming2[3], tcoming2[4], tcoming2[5])

            tnow = time.localtime(timenow)
            str_tnow_s = "%04d-%02d-%02d-%02d-%02d-%02d" % (tnow[0], tnow[1], tnow[2], tnow[3], tnow[4], tnow[5])
            
            for pkey in schedule:
                plstart = pkey[0:19]
                start = schedule[pkey]['start']
                end = schedule[pkey]['end']
          
                playedFlag = (pkey in playedItems) and playedItems[pkey].get("played", 0)
                
                if plstart == str_tcoming_s or (plstart < str_tcoming_s and plstart > str_tcoming2_s and not playedFlag):
                    logger.debug('Preparing to push playlist scheduled at: %s', pkey)
                    playlist = schedule[pkey]

                    currently_on_air = True

                    # We have a match, replace the current playlist and
                    # force liquidsoap to refresh.
                    if (self.push_liquidsoap(pkey, schedule, playlists) == 1):
                        logger.debug("Pushed to liquidsoap, updating 'played' status.")
                        # Marked the current playlist as 'played' in the schedule tracker
                        # so it is not called again in the next push loop.
                        # Write changes back to tracker file.
                        playedItems[pkey] = playlist
                        playedItems[pkey]['played'] = 1
                        schedule_tracker = open(self.schedule_tracker_file, "w")
                        pickle.dump(playedItems, schedule_tracker)
                        schedule_tracker.close()
                        logger.debug("Wrote schedule to disk: "+str(json.dumps(playedItems)))

                        # Call API to update schedule states
                        logger.debug("Doing callback to server to update 'played' status.")
                        self.api_client.notify_scheduled_item_start_playing(pkey, schedule)
                        
                start = schedule[pkey]['start']
                end = schedule[pkey]['end']

                if start <= str_tnow_s and str_tnow_s < end:
                    currently_on_air = True
        else:
            pass
            #logger.debug('Empty schedule')
            
        if not currently_on_air:
            tn = telnetlib.Telnet(LS_HOST, LS_PORT)
            tn.write('source.skip\n')
            tn.write('exit\n')
            tn.read_all()

    def push_liquidsoap(self, pkey, schedule, playlists):
        logger = logging.getLogger('push')

        try:
            playlist = playlists[pkey]

            #strptime returns struct_time in local time
            #mktime takes a time_struct and returns a floating point
            #gmtime Convert a time expressed in seconds since the epoch to a struct_time in UTC
            #mktime: expresses the time in local time, not UTC. It returns a floating point number, for compatibility with time().
            epoch_start = calendar.timegm(time.gmtime(time.mktime(time.strptime(pkey, '%Y-%m-%d-%H-%M-%S'))))

            #Return the time as a floating point number expressed in seconds since the epoch, in UTC.
            epoch_now = time.time()

            logger.debug("Epoch start: " + str(epoch_start))
            logger.debug("Epoch now: " + str(epoch_now))

            sleep_time = epoch_start - epoch_now;

            if sleep_time < 0:
                sleep_time = 0

            logger.debug('sleeping for %s s' % (sleep_time))
            time.sleep(sleep_time)

            tn = telnetlib.Telnet(LS_HOST, LS_PORT)

            #skip the currently playing song if any.
            logger.debug("source.skip\n")
            tn.write("source.skip\n")

            # Get any extra information for liquidsoap (which will be sent back to us)
            liquidsoap_data = self.api_client.get_liquidsoap_data(pkey, schedule)

            #Sending schedule table row id string.
            logger.debug("vars.pypo_data %s\n"%(str(liquidsoap_data["schedule_id"])))
            tn.write(("vars.pypo_data %s\n"%str(liquidsoap_data["schedule_id"])).encode('latin-1'))

            for item in playlist:
                annotate = str(item['annotate'])
                logger.debug(annotate)
                tn.write(('queue.push %s\n' % annotate).encode('latin-1'))
                tn.write(('vars.show_name %s\n' % item['show_name']).encode('latin-1'))

            tn.write("exit\n")
            logger.debug(tn.read_all())

            status = 1
        except Exception, e:
            logger.error('%s', e)
            status = 0
        return status

    def load_schedule_tracker(self):
        logger = logging.getLogger('push')
        #logger.debug('load_schedule_tracker')
        playedItems = dict()

        # create the file if it doesnt exist
        if (not os.path.exists(self.schedule_tracker_file)):
            try:
                logger.debug('creating file ' + self.schedule_tracker_file)
                schedule_tracker = open(self.schedule_tracker_file, 'w')
                pickle.dump(playedItems, schedule_tracker)
                schedule_tracker.close()
            except Exception, e:
                logger.error('Error creating schedule tracker file: %s', e)
        else:
            #logger.debug('schedule tracker file exists, opening: ' + self.schedule_tracker_file)
            try:
                schedule_tracker = open(self.schedule_tracker_file, "r")
                playedItems = pickle.load(schedule_tracker)
                schedule_tracker.close()
            except Exception, e:
                logger.error('Unable to load schedule tracker file: %s', e)

        return playedItems

    def run(self):
        loops = 0
        heartbeat_period = math.floor(30/PUSH_INTERVAL)
        logger = logging.getLogger('push')
        
        while True:
            if loops % heartbeat_period == 0:
                logger.info("heartbeat")
                loops = 0
            try: self.push('scheduler')
            except Exception, e:
                logger.error('Pypo Push Error, exiting: %s', e)
                sys.exit()
            time.sleep(PUSH_INTERVAL)
            loops += 1