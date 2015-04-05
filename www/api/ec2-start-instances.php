<?php
/*
 * Start EC2 instances
 *
 * Usage: ec2-start-instances.php?instance-id=[instance-id]
 *
 */

require 'AWSSDKforPHP/aws.phar';

use Aws\Ec2\Ec2Client;

// app config
require_once(dirname(__FILE__) . "/config.php");

// AWS config
$conf_array = parse_ini_file(APP_ROOT_DIR."/".AWS_CONFIG_FILE);


// get params
$instance_id = isset($_REQUEST['instance-id']) ? $_REQUEST['instance-id'] : '';
//$instance_id = 'i-f84748fd'

// check params
!empty($instance_id) || die('Instance ID が指定されていません。');


// get ec2 instances
$client = EC2Client::factory(array(
				   'key'    => $conf_array['aws_key'],
				   'secret' => $conf_array['aws_secret'],
				   'region' => $conf_array['aws_region'],
				   ));

$result = $client->startInstances(array(
				   'InstanceIds' =>  array($instance_id))
				   );


// output
header('Content-Type: application/json');

echo json_encode($result['StartingInstances']);

?>
