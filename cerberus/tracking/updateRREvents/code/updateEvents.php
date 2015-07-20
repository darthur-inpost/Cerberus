<?php
// Go through all of the shipments and either
// insert them into the returns_events table
// OR
// update the date and time held against the consignment
//
echo date('Y-m-d H:i:s') . " Start run.\n";

// Change to the current directory so that relative paths will work.
chdir(__DIR__);

require '../lib/dbClass.php';

$dbid1 = 'db_01';	// Shipments database
$dbid2 = 'db_00';	// szop_uk database

$config = parse_ini_file("../config/config.ini", TRUE);
date_default_timezone_set('Europe/London');
$currDT = date('Y-m-d H:i:s', time());

$errlogPath = $config['logs']['errorLog'];

// Establish DB connections
$conn1 = new dbClass($dbid1, $errlogPath); //connect to the remote DB server
$conn2 = new dbClass($dbid2, $errlogPath); //connect to the local DB server 

// Get the creation datetime for the last logged shipment
$conn1->query($config['queries']['qGetLastTS']);
$rows1 = $conn1->resultset(PDO::FETCH_ASSOC);
$lastLoggedDT = trim($rows1[0]['logged_at']);

echo "Last Logged date: " . $lastLoggedDT . "\n";

// Return all consignment nos updated since the last run
$conn1->query($config['queries']['qGetShipments']);
$conn1->bind(':last_logged', $lastLoggedDT);
$rows1 = $conn1->resultset(PDO::FETCH_ASSOC);

$count = 0;

foreach ($rows1 as $data)
{
	//echo "<br>Consignment_no:".$consignment_no;
	++$count;
	$consignment_no = trim($data['consignment_no']);
	$updated_at     = trim($data['updated_at']);     

	// For each shipment, get the time stamps for the three events.
	$conn2->query($config['queries']['qGetEvents']);
	$conn2->bind(':consignment_no', $consignment_no);
	$rows2 = $conn2->resultset(PDO::FETCH_ASSOC);
    
	$custDel    = trim($rows2[0]['customerdelivering']);
	$custStored = trim($rows2[0]['customerstored']);
	$custSent   = trim($rows2[0]['customersent']);
	$date       = new DateTime($custDel);
	$custDelMod = $date->format('Y-m-d H:i:s');
	echo "Count: ".$count." Consignment_no:".$consignment_no." CustomerDel: ".$custDelMod." CustomerStored: ".$custStored." CustomerSent: ".$custSent.".\n";

	// If the consignment exists in the event_log_rr_events table 
	// Count the number of rows for the consignment number.
 
	$conn1->query($config['queries']['qCountConsignment']);
	$conn1->bind(':consigment_num', $consignment_no);
	$rowExists = $conn1->resultset(PDO::FETCH_ASSOC);
	$existenceFlag = $rowExists[0][0];

	if($existenceFlag == '0')
	{
		//echo "consignment_no doesn't exist!";
		$conn1->query($config['queries']['qInsertEvents']);
		$conn1->bind(':consignment_no', $consignment_no);
		$conn1->bind(':cust_del', $custDelMod);
		$conn1->bind(':cust_sto', $custStored );
		$conn1->bind(':cust_snt', $custSent);
		$conn1->execute();
		$rowsAffected = $conn1->rowCount();
		echo "Inserted $rowsAffected rows.\n";
	}
	//otherwise, update the row for that consignment_no
	elseif($existenceFlag == '1')
	{
		$conn1->query($config['queries']['qUpdateEvents']);
		$conn1->bind(':consignment_num', $consignment_no);
		$conn1->bind(':cust_del', $custDelMod);
		$conn1->bind(':cust_sto', $custStored);
		$conn1->bind(':cust_snt', $custSent);
		$conn1->execute();
		$rowsAffected = $conn1->rowCount();
		echo "Updated $rowsAffected rows.\n";
	}
}

$conn1->query($config['queries']['qInsertLastTS']);
$conn1->bind(':curr_DT', $currDT);
$conn1->execute();

unset($conn1);
unset($conn2);

echo date('Y-m-d H:i:s') . " End date.\n";
?>
