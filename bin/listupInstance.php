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
use Aws\CloudWatch\CloudWatchClient;

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

exit;




#--------------------------------------------------------------

$client3 = CloudWatchClient::factory(array(
					   'key'    => $conf_array['aws_key'],
					   'secret' => $conf_array['aws_secret'],
					   'region' => 'us-east-1',
					   ));
$metrics = $client3->listMetrics(array('Namespace' => 'AWS/EC2'));
foreach ($metrics as $metric){
  var_dump($metric);
}
echo "xxx\n";
$x = $client3->getMetricStatistics(array(
					 'Namespace' => 'AWS/Billing',
					 'MetricName' => 'EstimatedCharges',
					 'StartTime' => '2013-06-29 00:00:00',
					 'EndTime' => '2013-06-29 11:15:10',
					 'Period' => 120,
					 'Statistics' => array("Average", "Maximum", "Minimum", "SampleCount", "Sum"),
					 'Dimensions' => array(
							       array('Name' => 'Currency','Value' => 'USD'),
							       array('Name' => 'ServiceName','Value' => 'AmazonEC2')
							       )
					 ));

foreach ($x as $a =>$b){
  echo "$a => $b\n";
  if( is_array($b) ){
    echo "OK $b\n";
    var_dump($b);
    foreach ($b as $key =>$value){
      echo "  $key => $value\n";
    }
  }
}
#var_dump($x);


