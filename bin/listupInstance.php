#!/usr/bin/php
<?php
require 'AWSSDKforPHP/aws.phar';

$conf_file = dirname(__FILE__) . "/../conf/config.ini";
$conf_array = parse_ini_file($conf_file);

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
use Aws\Rds\RdsClient;

$fileterStatus = "";
if(count($argv) > 1){
    $fileterStatus = $argv[1];
}

$action = "";
if(count($argv) > 2){
    $action= $argv[2];
}

$client = EC2Client::factory(array(
				   'key'    => $conf_array['aws_key'],
				   'secret' => $conf_array['aws_secret'],
				   'region' => $conf_array['aws_region'],
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


$client2 = RdsClient::factory(array(
				   'key'    => $conf_array['aws_key'],
				   'secret' => $conf_array['aws_secret'],
				   'region' => $conf_array['aws_region'],
				   ));


$result = $client2->DescribeDBInstances();
$x = $result['DBInstances'];
foreach ($x as $instance){
#  var_dump($instance);
  echo "DBInstanceIdentifier: " . $instance['DBInstanceIdentifier'] . PHP_EOL;
  echo "---> DBInstanceStatus: ". $instance['DBInstanceStatus'] . PHP_EOL;
  echo "---> DBName: " . $instance['DBName'] . PHP_EOL;
  echo "---> DBInstanceClass: " . $instance['DBInstanceClass'] . PHP_EOL;
  echo "---> Engine: " . $instance['Engine'] . PHP_EOL;

  $t = date('Ymd-His');

  $snapname = $instance['DBInstanceIdentifier']."-".$t;
  echo "[$snapname] [$action]\n";

  if($action == "start"){
  } else if($action == "stop"){
    if ( $instance['DBInstanceStatus'] == 'available' ){
      $client2->deleteDBInstance(array(
				       'DBInstanceIdentifier' => $instance['DBInstanceIdentifier'],
				       'FinalDBSnapshotIdentifier' => $snapname,
				       )
				 );
    }
  };
				   
}


