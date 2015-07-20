<?php
//
// Search for all customers for the Invoicing system.
//

// Chnge to the local directory so that relative paths will work.
chdir(__DIR__);

echo "Start run " . date("Y-m-d H:i:s") . "\n";

// This file also contains the FTP details.
require "../lib/databaseClass.php";
require "../lib/errorClass.php";
require "../lib/ftpClass.php";

$errorClass = new iperrorClass(2, "../logs/", "log-", "", 0);

if ($errorClass == null)
{
    // Failed to create error class
    die ("Failed to create error object.");
}

// Read the config file for this program
$configData = parse_ini_file("../config/config.ini", true);

if ($configData === false)
{
    $errorClass->message("error", "Failed to read config data.");
    die("Failed to read config data.");
}

$filename = "../output/customers" . date('Ymd') . ".csv";
$ftp_filename = "customer_" . date("dmY_Hi") . ".csv";

if(($file = fopen($filename, "wt")) == NULL)
{
	error_log("Failed to open file, $filename, for writing.");
	die;
}

try
{
    $databaseClass = new ipdatabaseClass("perseus_local",
                                $configData,
                                $errorClass);
}

catch(Exception $e)
{
    echo "Failed to open database connection, " . $e . "\n";
    die;
}

$databaseClass->query($configData["queries"]["qCustomerDetails"]);
$databaseClass->execute();

while ($row = $databaseClass->resultRow())
{
	//echo print_r($row);

	// output the line to the csv file.
	fwrite($file,
		'"' . $row['customer_id'] . '","' . $row['business_id'] . '",' .
		'"' . $row['bd_company_name'] . '","' . $row['bd_first_name'] . '",' .
		'"' . $row['bd_last_name'] . '","' . $row['email'] . '",' .
		'"' . $row['phone'] . '","0.00",' .
		'"' . $row['bd_postcode'] . '","' . $row['bd_city'] . '",' .
		'"' . $row['bd_addr1'] . '","' . $row['bd_addr2'] . '",' .
		'"DefaultPriceList' . "\"\n");
}

fclose($file);

$ftpClass = new ipftpClass(
                $configData['paradise_ftp_details']['ftpUname'],
                $configData['paradise_ftp_details']['ftpPass'],
                $configData['paradise_ftp_details']['ftpHost'],
                $configData['paradise_ftp_details']['ftpPort'],
                $configData['paradise_ftp_details']['ftpPath'],
                "",
                FTP_ASCII,
                $errorClass);

// Now FTP the file onto the server.
try
{
    $ftpClass->connect();

    $ret = $ftpClass->upload($filename, $ftp_filename);
}
catch(Exception $e)
{
	echo "Exception " . $e . "\n";
}

echo "End   run " . date("Y-m-d H:i:s") . "\n";
