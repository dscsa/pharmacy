#!/usr/bin/bash

CODE_DIR=/goodpill/webform
RESTART_SERVICES=1
WAIT_FOR_EMPTY=1

while getopts ":sw" opt; do
  case ${opt} in
    s ) RESTART_SERVICES=0
      ;;
    w ) WAIT_FOR_EMPTY=0;
      ;;
    \? ) echo <<<EOF
Usage: pa_updat.sh [-s] [-w] [-h]
    -s Do not restart the services after the code is pushed
    -w Do not Wait for the queus to empty befor pushing code
EOF
      ;;
  esac
done

##### Define Functions
are_queues_empty () {
    TOTAL_MESSAGES=0
    for message_count  in `aws sqs get-queue-attributes \
            --queue-url https://us-west-1.queue.amazonaws.com/213637289786/patient_requests.fifo \
            --attribute-names ApproximateNumberOfMessages ApproximateNumberOfMessagesNotVisible \
            | jq -r '.Attributes.ApproximateNumberOfMessages, .Attributes.ApproximateNumberOfMessagesNotVisible'`
    do
            TOTAL_MESSAGES=$((TOTAL_MESSAGES + message_count))
    done;

    for message_count  in `aws sqs get-queue-attributes \
            --queue-url https://us-west-1.queue.amazonaws.com/213637289786/sync_request.fifo \
            --attribute-names ApproximateNumberOfMessages ApproximateNumberOfMessagesNotVisible \
            | jq -r '.Attributes.ApproximateNumberOfMessages, .Attributes.ApproximateNumberOfMessagesNotVisible'`
    do
            TOTAL_MESSAGES=$((TOTAL_MESSAGES + message_count))
    done;
    if [[ $TOTAL_MESSAGES -gt 0 ]]
    then
       echo $TOTAL_MESSAGES;
       return 1;
    else
       return 0;
    fi
}

echo "Stopping Cron";
sudo service crond stop

#Time to Work
if [ $WAIT_FOR_EMPTY -eq 1 ]; then
    echo "Waiting for queues to empty"
    LAST_COUNT=0;
    until are_queues_empty
    do
            sleep 4;
            if [[ "$LAST_COUNT" -ne "$TOTAL_MESSAGES" ]]
            then
                echo $TOTAL_MESSAGES;
                LAST_COUNT=$TOTAL_MESSAGES;
            fi
    done;

    echo "Queues are empty, moving to release";
    tput bel
fi

echo "Stopping Supervisor";
sudo service supervisord stop

echo "Pulling Latest Code";
# Get the latest code
cd $CODE_DIR

#Put the results into a log file then print the most recent run
git tag prod-$(date +'%Y-%m-%d-%H%m%S')
echo "###########" >> /var/log/pharmacy.update.log; 
date >> /var/log/pharmacy.update.log; 
sudo git pull &>> /var/log/pharmacy.update.log; 
awk -v RS='(^|\n)###########\n' 'END{printf "%s", $0}' /var/log/pharmacy.update.log

echo "Updating Configs";
# Move the configs
sudo cp $CODE_DIR/configs/pharmacy.crons /etc/cron.d/pharmacy.crons
sudo cp $CODE_DIR/configs/supervisor.conf /etc/supervisord.conf

if [ $RESTART_SERVICES -eq 1 ]; then
    echo "Starting Services";
    #Restart the services
    sudo service crond start
    sudo service supervisord start
fi

echo "Code Release Complete"
tput bel
