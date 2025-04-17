#!/usr/bin/env bash
branch_prefix=ams_;
jira_slug=CAPXAMS

if [[ $# > 0 ]]
	then

    php artisan queue:work sqs --queue=$1 --timeout=100000 $2

fi
#file end
