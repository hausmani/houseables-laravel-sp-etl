#!/bin/bash
#if [[ $# -eq 1 ]]; then
#    echo "Processing Job From queue $1"
#    php artisan queue:work sqs --queue=$1 --timeout=100000 --once
#elif [[ $# -eq 2 ]]; then
#    echo "Processing Job From queue $1 driver $2"
#    php artisan queue:work $2 --queue=$1 --timeout=100000 --once
#else
#    echo "No Job Queue Provided to Process Job"
#fi

php artisan queue:work sqs --queue=sp-report-request-api --timeout=100000
