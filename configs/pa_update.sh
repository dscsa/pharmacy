#!/usr/bin/bash

CODE_DIR=/goodpill/webform
RESTART_SERVICES=1

while getopts ":s" opt; do
  case ${opt} in
    s ) RESTART_SERVICES=0
      ;;
    t ) # process option t
      ;;
    \? ) echo "Usage: cmd [-s]"
      ;;
  esac
done

# Get the latest code
cd $CODE_DIR
sudo git pull

# Move the configs
sudo cp $CODE_DIR/configs/pharmacy.crons /etc/cron.d/pharmacy.crons
sudo cp $CODE_DIR/configs/supervisor.conf /etc/supervisord.conf

if [ $RESTART_SERVICES -eq 1 ]; then
    #Restart the services
    sudo service crond restart
    sudo service supervisord restart
fi
