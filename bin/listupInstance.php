#!/usr/bin/php
<?php
require 'AWSSDKforPHP/aws.phar';

/**
 *[1]:instance-state-name
   The state of the instance.
   Type: String
   Valid values: pending | running | shutting-down | terminated | stopping | stopped

  [2]:action
   start or stop instances
   start : start stopped instances.
   stop : stop running instances.
 **/

use Aws\Ec2\Ec2Client;

$fileterStatus = "";
if(count($argv) > 1){
    $fileterStatus = $argv[1];
}

$action = "";
if(count($argv) > 2){
    $action= $argv[2];
}

$client = EC2Client::factory(array(
				  'key'    => '',
				  'secret' => '',
				  'region'=>'ap-northeast-1',
				   ));

if($fileterStatus != ""){
    $result = $client->DescribeInstances(array(
        'Filters' => array(
                array('Name' => 'instance-state-name', 'Values' => array($fileterStatus)),
        )
    ));
} else {
    $result = $client->DescribeInstances();
}

date_default_timezone_set('Asia/Tokyo');
echo "---- " . date('Y/m/d H:i:s') . " ----" . PHP_EOL;

$reservations = $result['Reservations'];
foreach ($reservations as $reservation) {
    $instances = $reservation['Instances'];
    foreach ($instances as $instance) {

        $instanceName = '';
        foreach ($instance['Tags'] as $tag) {
            if ($tag['Key'] == 'Name') {
                $instanceName = $tag['Value'];
            }
        }

        echo 'Instance Name: ' . $instanceName . PHP_EOL;
        echo '---> State: ' . $instance['State']['Name'] . PHP_EOL;
        echo '---> Instance ID: ' . $instance['InstanceId'] . PHP_EOL;
        echo '---> Image ID: ' . $instance['ImageId'] . PHP_EOL;
        echo '---> Private Dns Name: ' . $instance['PrivateDnsName'] . PHP_EOL;
        echo '---> Instance Type: ' . $instance['InstanceType'] . PHP_EOL;
        echo '---> Security Group: ' . $instance['SecurityGroups'][0]['GroupName'] . PHP_EOL;

        if($action == "start"){
           $startResult = $client->startInstances(array(
               'InstanceIds' =>  array($instance['InstanceId']))
           );
           //var_dump($startResult);
        } else if($action == "stop"){
           $stopResult = $client->stopInstances(array(
               'InstanceIds' =>  array($instance['InstanceId']))
           );
           //var_dump($stopResult);
        }
    }
}

echo "--------" . PHP_EOL;
