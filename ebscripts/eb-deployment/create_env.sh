#!/bin/bash
appDir="$1"
if [ "$1" == "" ] ; then
    appDir=/var/app/staging
fi
EB_META_DATA_FILE=$appDir/eb-metadata.json
ENV_VARS_FILE=$appDir/.env_from_console
sudo /opt/aws/bin/cfn-get-metadata -s $(</opt/elasticbeanstalk/config/ebenvinfo/stackid) -r AWSEBBeanstalkMetadata --region $(</opt/elasticbeanstalk/config/ebenvinfo/region) -k "AWS::ElasticBeanstalk::Ext">${EB_META_DATA_FILE}
ENV_VARS=$(jq -r '.Parameters.EnvironmentVariables' < ${EB_META_DATA_FILE})
echo ${ENV_VARS} | jq -r 'to_entries[] | [.value] | join("")' >${ENV_VARS_FILE}
aws ssm get-parameters-by-path --path /SPAPIETL --region us-east-1 |  jq -r '.Parameters | map(.Name+"="+.Value)| join("\n") | sub("/SPAPIETL/"; ""; "g")  ' > $appDir/.env
python3 $appDir/ebscripts/FixEnvVariable.py
aws s3 cp s3://bispoke-google-credentials/bq-credentials-sp.json $appDir/bq-credentials-sp.json
chmod 644 $appDir/bq-credentials-sp.json
