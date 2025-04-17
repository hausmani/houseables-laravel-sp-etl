#!/usr/bin/env bash
branch_prefix=ams_;
jira_slug=CAPXAMS

if [[ $# > 0 ]]
	then

    php artisan queue:work $2 --queue=$1 --timeout=100000

fi
#file end
