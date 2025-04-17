#!/usr/bin/env bash
branch_prefix=ams_;
jira_slug=CAPXAMS

if [[ $# > 0 ]]
	then

    php artisan queue:work --queue=$1 --timeout=100000 --once

fi
#file end
