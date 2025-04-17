#!/bin/bash
setfacl -Rd -m g:webapp:rwx /var/app/current/public/downloads
setfacl -Rd -m g:webapp:rwx /var/app/current/storage/logs/

setfacl -Rd -m u:ec2-user:rwx /var/app/current/public/downloads
setfacl -Rd -m u:ec2-user:rwx /var/app/current/storage/logs/

# setting permissions
chmod -R 777 /var/app/current/storage
chmod -R 777 /var/app/current/storage/framework/sessions
chmod -R 777 /var/app/current/bootstrap/cache
chmod -R 777 /var/app/current/storage/logs
chmod -R 777 /var/app/current/public/downloads

file=/var/app/current/database/database.sqlite
if [ ! -e "$file" ]; then
    # If the file doesn't exist, create it using touch
    touch "$file"
    echo "Database File created: $file"
else
    echo "Database File already exists: $file"
fi
chmod -R 777 $file
