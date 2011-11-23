#!/bin/bash -e
#-e Causes bash script to exit if any of the installers
#return with a non-zero return value.

if [ `whoami` != 'root' ]; then
    echo "Please run as root user."
    exit 1
fi

#copy files to 
## /etc/airtime
#+ /etc/apache2/sites-available/airtime
#+ /etc/apache2/sites-enabled/airtime
## /etc/cron.d/
## /etc/init.d/
## /etc/monit/conf.d/
# /usr/lib/airtime/airtime_virtualenv
## /usr/lib/airtime/api_clients
## /usr/lib/airtime/media-monitor
# /srv/airtime/stor
## /usr/lib/airtime/pypo
## /usr/lib/airtime/show-recorder
## /usr/lib/airtime/utils
## /usr/bin/airtime-*
## /usr/share/airtime
## /var/log/airtime
## /var/tmp/airtime

# Absolute path to this script, e.g. /home/user/bin/foo.sh
SCRIPT=`readlink -f $0`
# Absolute path this script is in, thus /home/user/bin
SCRIPTPATH=`dirname $SCRIPT`

AIRTIMEROOT=$SCRIPTPATH/../../

echo "* Creating /etc/airtime"
mkdir -p /etc/airtime
if [ ! -e /etc/airtime/airtime.conf ]; then
    cp $AIRTIMEROOT/airtime_mvc/build/airtime.conf /etc/airtime
fi

if [ ! -e /etc/airtime/api_client.cfg ]; then
cp $AIRTIMEROOT/python_apps/api_clients/api_client.cfg /etc/airtime
fi

if [ ! -e /etc/airtime/recorder.cfg ]; then
cp $AIRTIMEROOT/python_apps/show-recorder/recorder.cfg /etc/airtime
fi

if [ ! -e /etc/airtime/media-monitor.cfg ]; then
cp $AIRTIMEROOT/python_apps/media-monitor/media-monitor.cfg /etc/airtime
fi

if [ ! -e /etc/airtime/pypo.cfg ]; then
cp $AIRTIMEROOT/python_apps/pypo/pypo.cfg /etc/airtime
fi

if [ ! -e /etc/airtime/liquidsoap.cfg ]; then
cp $AIRTIMEROOT/python_apps/pypo/liquidsoap_scripts/liquidsoap.cfg /etc/airtime
fi

echo "* Creating /etc/cron.d/airtime-crons"
HOUR=$(($RANDOM%24))
MIN=$(($RANDOM%60))
echo "$MIN $HOUR * * * root /usr/lib/airtime/utils/phone_home_stat" > /etc/cron.d/airtime-crons

#virtualenv_bin="/usr/lib/airtime/airtime_virtualenv/bin/"
#. ${virtualenv_bin}activate

echo "* Creating /usr/lib/airtime"
python $AIRTIMEROOT/python_apps/api_clients/install/api_client_install.py
python $AIRTIMEROOT/python_apps/pypo/install/pypo-copy-files.py
python $AIRTIMEROOT/python_apps/media-monitor/install/media-monitor-copy-files.py
python $AIRTIMEROOT/python_apps/show-recorder/install/recorder-copy-files.py

cp -R $AIRTIMEROOT/utils /usr/lib/airtime

echo "* Creating symbolic links in /usr/bin"
#create symbolic links
ln -sf /usr/lib/airtime/utils/airtime-import/airtime-import /usr/bin/airtime-import
ln -sf /usr/lib/airtime/utils/airtime-update-db-settings /usr/bin/airtime-update-db-settings
ln -sf /usr/lib/airtime/utils/airtime-check-system /usr/bin/airtime-check-system
ln -sf /usr/lib/airtime/utils/airtime-log /usr/bin/airtime-log

echo "* Creating /usr/share/airtime"
rm -rf "/usr/share/airtime"
mkdir -p /usr/share/airtime
cp -R $AIRTIMEROOT/airtime_mvc/* /usr/share/airtime/

echo "* Creating /var/log/airtime"
mkdir -p /var/log/airtime

echo "* Creating /var/tmp/airtime"
mkdir -p /var/tmp/airtime

#Finished copying files