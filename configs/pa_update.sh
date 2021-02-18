#!/usr/bin/bash

CODE_DIR=/goodpill/webform

# Get the latest code
cd $CODE_DIR
sudo git pull

# Move the configs
sudo cp $CODE_DIR/configs/pharmacy.crons /etc/cron.d/pharmacy.crons
sudo cp $CODE_DIR/configs/supervisor.conf /etc/supervisord.conf

#Restart the services
sudo service crond restart
sudo service supervisord restart
