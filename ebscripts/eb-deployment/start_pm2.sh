#!/bin/bash

echo "Stopping pm2"
pm2 stop all --silent 2>/tmp/pm2.txt
pm2 delete all --silent 2>/tmp/pm2.txt
environment_name=$(/opt/elasticbeanstalk/get-beanstalk-env-name.sh)
echo "Copying configuration files from {$environment_name}"
cp /var/app/current/pm2/$environment_name/* /var/app/current/pm2/running/
for filename in /var/app/current/pm2/running/*.yml; do
    sudo -u ec2-user pm2 start $filename --watch=false  --force
done
