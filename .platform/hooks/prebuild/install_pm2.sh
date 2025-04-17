#!/usr/bin/env bash
if [ ! -f /usr/bin/node ]; then
    echo "installing node"
    curl -sL https://rpm.nodesource.com/setup_16.x | bash -
    yum -y install nodejs
else
    echo "Node already installed"
fi
npm install -g npm@latest
npm install -g pm2 2>/tmp/pm2.txt
#pm2 stop all --silent
