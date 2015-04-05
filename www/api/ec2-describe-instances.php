<?php
/*
 * Get EC2 instances
 */

require 'AWSSDKforPHP/aws.phar';

use Aws\Ec2\Ec2Client;

// app config
require_once(dirname(__FILE__) . "/config.php");

// AWS config
$conf_array = parse_ini_file(APP_ROOT_DIR."/".AWS_CONFIG_FILE);


// get ec2 instances
$client = EC2Client::factory(array(
				   'key'    => $conf_array['aws_key'],
				   'secret' => $conf_array['aws_secret'],
				   'region' => $conf_array['aws_region'],
				   ));

$result = $client->DescribeInstances();

$instances = array();
foreach ($result['Reservations'] as $reservation) {
    foreach ($reservation['Instances'] as $instance) {

        $instanceName = '';
        foreach ($instance['Tags'] as $tag) {
            if ($tag['Key'] == 'Name') {
                $instanceName = $tag['Value'];
            }
        }

	$obj['id'] = $instance['InstanceId'];
	$obj['name'] = $instanceName;
	$obj['state'] = $instance['State']['Name'];
	$obj['type'] = $instance['InstanceType'];
	$obj['ipaddress'] = $instance['PrivateIpAddress'];
	$obj['ipaddress_public'] = $instance['PublicIpAddress'];

	$obj['dns'] = $instance['PrivateDnsName'];
	$obj['imageid'] = $instance['ImageId'];
	$obj['group'] = $instance['SecurityGroups'][0]['GroupName'];

	array_push($instances, $obj);
    }
}

// output
header('Content-Type: application/json');

//echo json_encode($result['Reservations']);
echo json_encode($instances);

?>
