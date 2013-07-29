#!/usr/bin/php
<?php namespace glueaws\bin;
require_once 'AWSSDKforPHP/aws.phar';

$dir = realpath(dirname( __FILE__));
require_once $dir."/../services/RdsService.php";
use glueaws\services\RdsService;

# check parameter
$expectedParams = array("list", "start", "stop", "modify", "deleteOldSnapshots");
if(!checkParameters($argv, $expectedParams)){
    echo "usage: RdsManager.php (" . join("|", $expectedParams) . ")" . PHP_EOL . PHP_EOL;
    exit(1);
}

# setup
$conf_file = dirname(__FILE__) . "/../conf/config.ini";
$conf_array = parse_ini_file($conf_file);

date_default_timezone_set('Asia/Tokyo');
echo "---- " . date('Y/m/d H:i:s') . " ----" . PHP_EOL;

$dbInstanceId = $conf_array['rds_instance_id'];
$dbSecurityGroupId = $conf_array['rds_vpc_security_group_id'];
$dbSnapBackupGen = (int)$conf_array['rds_snap_backup_generation'];
$dbRestoreWaittime= (int)$conf_array['rds_restore_waittime'];

$rdsService = new RdsService($conf_array['aws_key'], $conf_array['aws_secret'], $conf_array['aws_region']);

$param1 = $argv[1];

#show / start|stop instance
try{
    if($param1 == "list"){
        # show instances and snapshots
        echo "---- DB Instantces ----" . PHP_EOL;
        $rdsInstances = $rdsService->getInstances();
        foreach ($rdsInstances as $instance){
        #  var_dump($instance);
            echo "DBInstanceIdentifier: " . $instance->getInstanceIdentifier() . PHP_EOL;
            echo "---> DBInstanceStatus: ". $instance->getInstanceStatus() . PHP_EOL;
            echo "---> DBName: " . $instance->getName() . PHP_EOL;
            echo "---> DBInstanceClass: " . $instance->getInstanceClass() . PHP_EOL;
            echo "---> Engine: " . $instance->getEngine() . PHP_EOL;
        }
        
        echo PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL;
        echo "---- target DB Instantce (" . $dbInstanceId . ") ----" . PHP_EOL;
        $instance= $rdsService->getInstanceByInstanceId($dbInstanceId);
        if(!is_null($instance)){
            echo "DBInstanceIdentifier: " . $instance->getInstanceIdentifier() . PHP_EOL;
            echo "---> DBInstanceStatus: ". $instance->getInstanceStatus() . PHP_EOL;
            echo "---> DBName: " . $instance->getName() . PHP_EOL;
            echo "---> DBInstanceClass: " . $instance->getInstanceClass() . PHP_EOL;
            echo "---> Engine: " . $instance->getEngine() . PHP_EOL;
        }
        
        echo PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL;
        echo "---- DB Snapshots----" . PHP_EOL;
        $rdsSnapshots = $rdsService->getAllAvailableSnapshots();
        foreach ($rdsSnapshots as $instance){
            echo "DBSnapshotIdentifier: " . $instance->getSnapshotIdentifier() . PHP_EOL;
            echo "---> DBInstanceIdentifier: ". $instance->getInstanceIdentifier() . PHP_EOL;
            echo "---> SnapshotCreateTime : " . $instance->getSnapshotCreateTime() . PHP_EOL;
            echo "---> Engine: " . $instance->getEngine() . PHP_EOL;
        }
        
        echo PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL;
        echo "---- target DB Snapshots (" . $dbInstanceId . ") ----" . PHP_EOL;
        $rdsSnapshots = $rdsService->getAvailableSnapshotsByInstanceId($dbInstanceId);
        foreach ($rdsSnapshots as $instance){
            echo "DBSnapshotIdentifier: " . $instance->getSnapshotIdentifier() . PHP_EOL;
            echo "---> DBInstanceIdentifier: ". $instance->getInstanceIdentifier() . PHP_EOL;
            echo "---> SnapshotCreateTime : " . $instance->getSnapshotCreateTime() . PHP_EOL;
            echo "---> Engine: " . $instance->getEngine() . PHP_EOL;
        }
        
        echo PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL;
        echo "---- latest target DB Snapshots (" . $dbInstanceId . ") ----" . PHP_EOL;
        $instance = $rdsService->getLatestAvailableSnapshotsByInstanceId($dbInstanceId);
        if(!is_null($instance)){
            echo "DBSnapshotIdentifier: " . $instance->getSnapshotIdentifier() . PHP_EOL;
            echo "---> DBInstanceIdentifier: ". $instance->getInstanceIdentifier() . PHP_EOL;
            echo "---> SnapshotCreateTime : " . $instance->getSnapshotCreateTime() . PHP_EOL;
            echo "---> Engine: " . $instance->getEngine() . PHP_EOL;
        }

    }else if($param1 == "start"){
        # startup rds instance and modify security group.
        echo "---- restore instances (glue) ----" . PHP_EOL;
        $rdsService->restoreLatestSnapshot($dbInstanceId);
        echo PHP_EOL . PHP_EOL;

        # wait for restore
        echo " waiting for restore.... (" . $dbRestoreWaittime . " sec)";
        sleep($dbRestoreWaittime);

        echo PHP_EOL . PHP_EOL;
        echo "---- modify instance security group (glue) ----" . PHP_EOL;
        $rdsService->modifyInstanceSecurityGroup($dbInstanceId, $dbSecurityGroupId);

    }else if($param1 == "modify"){
        # modify security group.
        echo "---- modify instance security group (glue) ----" . PHP_EOL;
        $rdsService->modifyInstanceSecurityGroup($dbInstanceId, $dbSecurityGroupId);

    }else if($param1 == "stop"){
        # stop rds instance.
        echo "---- stop instances ----" . PHP_EOL;
        $rdsService->stopAndSaveAllInstances();

    }else if($param1 == "deleteOldSnapshots"){
        # delete old rds snapshot
        echo "---- delete old snapshot ----" . PHP_EOL;
        $rdsService->deleteOldAvailableSnapshots($dbInstanceId, $dbSnapBackupGen);

    }
}catch(\Exception $ex){
    echo "ERROR : " .  $ex->getMessage() . PHP_EOL;
    exit(1);
}

exit(0);

function checkParameters($inParams, $expectedParams){
    if(count($inParams) != 2){
        return false;
    }
    foreach($expectedParams as $param){
        if(array_search($param, $inParams)){
            return true;
        }
    }
    return false;
}



?>

