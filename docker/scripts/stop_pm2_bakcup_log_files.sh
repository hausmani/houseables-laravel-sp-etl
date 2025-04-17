#!/usr/bin/env bash
export TZ=America/Los_Angeles
#stopping pm2
php artisan pm2:stop
sleep 10

stop_date=$(date -d "26 minutes" "+%s")
retry=1
while ([ $retry == 1 ] && [ $(date "+%s") -lt ${stop_date} ]); do
    total_jobs=$(ps aux | grep 'queue:work' | grep -v 'grep' | wc -l)
    echo "Total jobs $total_jobs in automation worker"
    if [ $total_jobs == "0" ]; then
        retry=0
    else
        echo "Going to sleep as $total_jobs process are still running"
        sleep 10
    fi
done

pm2 list >/var/www/html/storage/logs/worker_list.log

INSTANCEID="temp_automation"
EC2_ENV_NAME="capx"

if [[ $# -gt 0 ]]; then
    INSTANCEID=$1
fi
if [[ $# -gt 1 ]]; then
    EC2_ENV_NAME=$2
fi
S3BUCKET="capx-ams"
today_date=$(date -u +"%Y-%m-%d")
today_time=$(date -u +"%H-%M")

for filename in /var/www/html/storage/logs/*.log; do
    aws s3 cp $filename s3://${S3BUCKET}/ec2-termination-log/${EC2_ENV_NAME}/${today_date}/${today_time}_${INSTANCEID}/$(basename $filename)
done
