#!/bin/bash
docker build -t sp-api-etl-laravel .
docker tag sp-api-etl-laravel:latest 767397866624.dkr.ecr.us-east-1.amazonaws.com/sp-api-etl-laravel:latest
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin 767397866624.dkr.ecr.us-east-1.amazonaws.com
docker push 767397866624.dkr.ecr.us-east-1.amazonaws.com/sp-api-etl-laravel:latest
