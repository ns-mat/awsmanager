<?php namespace glueaws\services;
require_once 'AWSSDKforPHP/aws.phar';
use Aws\Rds\RdsClient;

class RdsService {
    private $client;

    public function __construct($awsKey, $awsSecret, $awsRegion) {
        $this->client = RdsClient::factory(array(
                   'key'    => $awsKey,
                   'secret' => $awsSecret,
                   'region' => $awsRegion,
                   ));
    }

    public function getInstances(){
        $detail = $this->client->DescribeDBInstances();
        $instances = $detail['DBInstances'];
        return RdsInstance::convert($instances);
    }

    public function getInstanceByInstanceId($instanceId){
        $instances = $this->getInstances();
        $instances = array_filter($instances, function($v) use($instanceId){
                return $v->getInstanceIdentifier() == $instanceId;
            }
        );
        return (count($instances) > 0) ? $instances[0] : null;
    }

    private function stopAndSaveInstance($instance){
        if(!is_null($instance) && $instance->isAvailable()){
            $snapshotName = $instance->getInstanceIdentifier() . "-" . date('Ymd-His');
            echo "delete instance[" . $instance->getInstanceIdentifier() . "]. create snapshot[" . $snapshotName . "]." . PHP_EOL;
            $this->client->deleteDBInstance(array(
                'DBInstanceIdentifier' => $instance->getInstanceIdentifier(),
                'FinalDBSnapshotIdentifier' => $snapshotName));
        }else{
            echo "can't stop instance[" . $instance->getInstanceIdentifier() . "]. instance status is " . $instance->getInstanceStatus() . PHP_EOL;
            throw new \Exception("can't stop instance[" . $instance->getInstanceIdentifier() . "]. instance status is " . $instance->getInstanceStatus());
        }
    }

    public function stopAndSaveInstanceiByInstanceId($instanceId){
        $instance = getInstanceByInstanceId($instanceId);
        $this->stopAndSaveInstance($instance);
    }
    
    public function stopAndSaveAllInstances(){
        $instances = $this->getInstances();
        foreach($instances as $instance){
            $this->stopAndSaveInstance($instance);
        }
    }

    public function restoreLatestSnapshot($instanceId){
        $instance = $this->getInstanceByInstanceId($instanceId);
        if(is_null($instance)){
            $latestSnapshot = $this->getLatestAvailableSnapshotsByInstanceId($instanceId);
            if(!is_null($latestSnapshot)){
                echo "restore snapshot[" . $latestSnapshot->getSnapshotIdentifier() . "]." . PHP_EOL;
                $this->client->restoreDBInstanceFromDBSnapshot(array(
                    'DBSnapshotIdentifier' => $latestSnapshot->getSnapshotIdentifier(),
                    'DBInstanceIdentifier' => $instanceId,
                    'AvailabilityZone' => $latestSnapshot->getAvailabilityZone()
                ));
            }else{
                echo "instance[" . $instanceId . "] is already running.";
                throw new \Exception("can't restore snapshot of instance[" . $instanceId . "]. snapshot is not available.");
            }
        }else{
            echo "snapshot is not available.";
            throw new \Exception("can't restore snapshot of instance[" . $instanceId . "]. instance is already running.");
        }
    }

    public function modifyInstanceSecurityGroup($instanceId, $securityGroupId){
        $instance = $this->getInstanceByInstanceId($instanceId);
        if(!is_null($instance) && $instance->isAvailable()){
            if(!$instance->hasSecurityGroups($securityGroupId) && $instance->isAvailable()){
                echo "modify security group[" . $securityGroupId . "] of instance[" . $instanceId . "]" . PHP_EOL;
                $this->client->ModifyDBInstance(array(
                    'DBInstanceIdentifier' => $instanceId,
                    'VpcSecurityGroupIds' => array($securityGroupId)));
            }else{
                throw new \Exception("can't modify security group. security group of instance[" . $instanceId . "] is already set.");
            }
        }else{
            throw new \Exception("can't modify security group. instance[" . $instanceId . "] is not available.");
        }
    }

    public function deleteOldAvailableSnapshots($instanceId, $left){
        $availableSnaps = $this->getAvailableSnapshotsByInstanceId($instanceId);
        //念のため作成年月降順でソートし直す
        usort($availableSnaps, function ($a, $b) {
                if( $a->getSnapshotCreateTime() == $b->getSnapshotCreateTime()){
                    return 0;
                }
                return ($a->getSnapshotCreateTime() > $b->getSnapshotCreateTime()) ? -1 : 1;
            });
        $latestSnap = $this->getLatestAvailableSnapshotsByInstanceId($instanceId);
        //最新のスナップショットが削除されないようにする
        $startPos = $left > 1 ? $left : 1;
        $endPos = count($availableSnaps);
        for($i = $startPos; $i < $endPos; $i++){
            $snap = $availableSnaps[$i];
            if($snap->getSnapshotIdentifier() != $latestSnap->getSnapshotIdentifier()){
                $this->client->deleteDBSnapshot(array(
                    'DBSnapshotIdentifier' => $snap->getSnapshotIdentifier(),
                    'DBInstanceIdentifier' => $instanceId));
                echo "DBSnapshotIdentifier[" . $snap->getSnapshotIdentifier() . "] is deleting." . PHP_EOL;
            }
        }
    }

    private function getSnapshots($filter, $sort){
        $detail = $this->client->describeDBSnapshots();
        $snapshots= RdsSnapshot::convert($detail['DBSnapshots']);
        if(!is_null($filter)){
            $snapshots = array_filter($snapshots, $filter);
        }
        if(!is_null($sort)){
            usort($snapshots, $sort);
        }
        return $snapshots;
    }
    
    public function getAllAvailableSnapshots(){
        return $this->getSnapshots(
            function ($v) {
                return $v->isAvailable();
            }, function ($a, $b) {
                if( $a->getSnapshotCreateTime() == $b->getSnapshotCreateTime()){
                    return 0;
                }
                return ($a->getSnapshotCreateTime() > $b->getSnapshotCreateTime()) ? -1 : 1;
            });
    }

    public function getAvailableSnapshotsByInstanceId($instanceId){
        return $this->getSnapshots(
            function ($v) use($instanceId) {
                return $v->getInstanceIdentifier() == $instanceId && $v->isAvailable();
            }, function ($a, $b) {
                if( $a->getSnapshotCreateTime() == $b->getSnapshotCreateTime()){
                    return 0;
                }
                return ($a->getSnapshotCreateTime() > $b->getSnapshotCreateTime()) ? -1 : 1;
            });
    }
    public function getLatestAvailableSnapshotsByInstanceId($instanceId){
        $ordered = $this->getAvailableSnapshotsByInstanceId($instanceId);
        return count($ordered) > 0 ? $ordered[0] : null;
    }
}


class RdsInstance {
    public $datasource;

    //RDSClient->DescribeDBInstances() の結果
    public static function convert(array $instances) {
        $results = array();
        foreach ($instances as $instance){
            $results[] = new RdsInstance($instance);
        }
        return $results;
    }

    //RDSClient->DescribeDBInstances() の結果の１要素
    public function __construct(array $instance) {
        $this->datasource = $instance;
    }

    public function getInstanceIdentifier(){
        return $this->datasource['DBInstanceIdentifier'];
    }

    public function getInstanceStatus(){
        return $this->datasource['DBInstanceStatus'];
    }

    public function getName(){
        return $this->datasource['DBName'];
    }

    public function getInstanceClass(){
        return $this->datasource['DBInstanceClass'];
    }

    public function getEngine(){
        return $this->datasource['Engine'];
    }
    
    public function hasSecurityGroups($securityGroupId){
        $securityGroups = $this->datasource['VpcSecurityGroups'];
        $securityGroups = array_filter($securityGroups, function($v) use($securityGroupId) {
            return $v['VpcSecurityGroupId'] == $securityGroupId;
            });
        return count($securityGroups) > 0; 
    }

    public function isAvailable(){
        return $this->getInstanceStatus() == 'available';
    }
}

class RdsSnapshot {
    public $datasource;
    public static function convert(array $instances) {
        $results = array();
        foreach ($instances as $instance){
            $results[] = new RdsSnapshot($instance);
        }
        return $results;
    }
    public function __construct(array $instance) {
        $this->datasource = $instance;
    }

    public function getSnapshotIdentifier(){
        return $this->datasource['DBSnapshotIdentifier'];
    }
    public function getInstanceIdentifier(){
        return $this->datasource['DBInstanceIdentifier'];
    }
    public function getSnapshotCreateTime(){
        return $this->datasource['SnapshotCreateTime'];
    }
    public function getEngine(){
        return $this->datasource['Engine'];
    }
    public function getAvailabilityZone(){
        return $this->datasource['AvailabilityZone'];
    }
    public function getStatus(){
        return $this->datasource['Status'];
    }
    public function isAvailable(){
        return $this->getStatus() == 'available';
    }
}
?>
