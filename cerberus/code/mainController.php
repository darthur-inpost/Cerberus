<?php
// This file is the main controller for the Cerberus system

// NB using a shared class for ANY database should be done with extreme care.
// There is only one set of data structures to hold a queries response.
// I.e. one query can overwrite the data from a previous one.
// If a function needs to parse data give it it's own new class to use.

///
// regularSearch
//
// @param string The folder to look in
// @param string The regular expresion to look for in the file name
//
function regularSearch($folder, $pattern)
{
    if (isset($folder) && is_readable($folder))
    {
        $folder = realpath($folder);
    }
    else
    {
        return false;
    }

    $dir = new RecursiveDirectoryIterator($folder);
    $ite = new RecursiveIteratorIterator($dir);

    $files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
    $fileList = array();

    foreach($files as $file)
    {
        if (isset($file[0]) && is_file($file[0]))
        {
            $fileList[] = $file[0];
            //echo "File found " . $file[0] . "\n";
        }
    }
    return $fileList;
}

///
// processClientConfig
//
// @param mixed    The client configuration data
// @return boolean true if any job is ready for this hour
//
function processClientConfig($configData, $key)
{
    global $jobList;
    $types = array();

    //echo "Client config = " . print_r($configData) . "\n";

    // Start by finding any jobs that need to be run in this hour.
    if ($configData['reporting_freq']['dailyReport'] == true &&
        $configData['daily_reports']['hour'] == date('H'))
    {
        //echo "Daily report found.\n";

        // We have a report for this hour
        foreach ($configData['daily_reports']['type'] as $type)
        {
            $types[] = array('time'      => $configData['daily_reports']['mins'],
                            'type'       => $type,
                            'duration'   => 1,
                            'fileFormat' => $configData['file_format']['formatedAs'],
                            'fileName'   => "",
                            'shortFileName' => "",
                            'invoiceFileName'      => "",
                            'invoiceShortFileName' => "",
                            'key'        => $key,
                            'customerId' => $configData['profile']['customerID']
                        );
        }
    }

    if ($configData['reporting_freq']['weeklyReport'] == true &&
        $configData['weekly_reports']['day'] == date('N') &&
        $configData['weekly_reports']['hour'] == date('H'))
    {
        //echo "Found weekly\n";

        // We have a report for this weekday and hour
        foreach ($configData['weekly_reports']['type'] as $type)
        {
            $types[] = array('time'      => $configData['weekly_reports']['mins'],
                            'type'       => $type,
                            'duration'   => 7,
                            'fileFormat' => $configData['file_format']['formatedAs'],
                            'fileName'   => "",
                            'shortFileName' => "",
                            'invoiceFileName'      => "",
                            'invoiceShortFileName' => "",
                            'key'        => $key,
                            'customerId' => $configData['profile']['customerID']
                        );
        }
    }

    if ($configData['reporting_freq']['monthlyReport'] == true &&
        $configData['monthly_reports']['day'] == date('j') &&
        $configData['monthly_reports']['hour'] == date('H'))
    {
        //echo "Found a monthly report to add...\n";

        // We have a report for this month day and hour
        foreach ($configData['monthly_reports']['type'] as $type)
        {
            $types[] = array('time'      => $configData['monthly_reports']['mins'],
                            'type'       => $type,
                            'duration'   => 30,
                            'fileFormat' => $configData['file_format']['formatedAs'],
                            'fileName'   => "",
                            'shortFileName' => "",
                            'invoiceFileName'      => "",
                            'invoiceShortFileName' => "",
                            'key'        => $key,
                            'customerId' => $configData['profile']['customerID']
                        );
        }
    }

    if (count($types) > 0)
    {
        foreach ($types as $type)
        {
            $jobList[] = $type;
        }
        return true;
    }
    else
    {
        return false;
    }
}

///
// scheduleReports
//
function scheduleReports()
{
    global $jobList;

    // Check if we have been delayed and the report should simply run now.
    // TODO change this to something better.
    foreach ($jobList as $job)
    {
        run($job);
    }
}

///
// run
//
// Job data Array (
//          [time] => 30
//          [type] => R7_Invoice
//          [duration] => 7
//          [fileFormat] => XML
//          [key] => 0
//          [customerId] => UK864
//  )
function run($job)
{
    global $errorClass;
    global $configData;
    global $queries;
    global $databaseClass;
    global $szopDbClass;
    global $StartRunTime;

    //echo "Job data = " . print_r($job) . "\n";
    //die;
    // Calculate the start and end time for the reports.
    // The time differences are calculated to include the FULL number of
    // seconds. I.e. one whole day = 86400 seconds and the time difference is
    // set at that.
    switch($job["duration"])
    {
        case 1:  // Daily, last 24 hours of previous day
            $startDate = date("Y-m-d H:i:s", mktime(
                0, 0, 0,
                date("m", $StartRunTime),
                date("d", $StartRunTime) - 1,
                date("Y", $StartRunTime) ));
            $endDate = date("Y-m-d H:i:s", mktime(
                0, 0, 0,
                date("m", $StartRunTime),
                date("d", $StartRunTime),
                date("Y", $StartRunTime) ));
            break;
        case 7:  // Weekly, last 7 days of the previous whole week
            // Starting with a Monday to a Sunday
            $currWeekDay = date("w", $StartRunTime);

            if ($currWeekDay == 0)
            {
                // Sunday
                $daysToRemove = 13;
            }
            else
            {
                $daysToRemove = 6 + $currWeekDay;
            }

            $startDate = date("Y-m-d H:i:s", mktime(
                0, 0, 0,
                date("m", $StartRunTime),
                date("d", $StartRunTime) - $daysToRemove,
                date("Y", $StartRunTime) ));
            $endDate = date("Y-m-d H:i:s", mktime(
                0, 0, 0,
                date("m", $StartRunTime),
                date("d", $StartRunTime) - $daysToRemove + 7,
                date("Y", $StartRunTime) ));
            break;
        case 30: // Monthly, last month
            $startDate = date("Y-m-d H:i:s", mktime(
                0, 0, 0,
                date("m", $StartRunTime) - 1,
                1,
                date("Y", $StartRunTime) ));
            $endDate = date("Y-m-d H:i:s", mktime(
                0, 0, 0,
                date("m", $StartRunTime),
                1,
                date("Y", $StartRunTime) ));
            break;
        default:
            // Don't know what the duration is, do nothing instead.
            return;
            break;
    }

    echo "Start Date " . $startDate . "\nEnd Date " . $endDate . "\n";

    $headers   = "";
    $xmlWidth  = array();
    $xmlType   = array();
    $coreQuery = true;
    $name      = "";

    // Set the correct query for the report
    // The situation is more complex for ad-hoc reports. We have to pickup
    // the correct bind variables to ensure that the query will work.
    // For the main queries they all take customer_id, start_date and
    // end_date.
    //
    switch($job["type"])
    {
        case 'R1_RetentionPeriod':
            echo "Retention Period\n";
            if ($queries["dbRetentionPeriod"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $databaseClass->query($queries["qRetentionPeriod"]);
            $headers      = $queries["dRetentionPeriod"];
            $xmlWidth     = $queries["xmlRetentionPeriodColumnWidth"];
            $xmlType      = $queries["xmlRetentionPeriodColumnType"];
            break;
        case 'R2_Delivered':
            echo "Delivered\n";
            if ($queries["dbDelivered"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $databaseClass->query($queries["qDelivered"]);
            $headers      = $queries["dDelivered"];
            $xmlWidth     = $queries["xmlDeliveredColumnWidth"];
            $xmlType      = $queries["xmlDeliveredColumnType"];
            break;
        case 'R3_Stored':
            echo "Stored\n";
            if ($queries["dbStored"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $databaseClass->query($queries["qStored"]);
            $headers      = $queries["dStored"];
            $xmlWidth     = $queries["xmlStoredColumnWidth"];
            $xmlType      = $queries["xmlStoredColumnType"];
            break;
        case 'R4_Collected':
            echo "Collected\n";
            if ($queries["dbCollected"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $databaseClass->query($queries["qCollected"]);
            $headers      = $queries["dCollected"];
            $xmlWidth     = $queries["xmlCollectedColumnWidth"];
            $xmlType      = $queries["xmlCollectedColumnType"];
            break;
        case 'R5_Expired':
            echo "Expired\n";
            if ($queries["dbExpired"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $databaseClass->query($queries["qExpired"]);
            $headers      = $queries["dExpired"];
            $xmlWidth     = $queries["xmlExpiredColumnWidth"];
            $xmlType      = $queries["xmlExpiredColumnType"];
            break;
        case 'R6_Exception':
            echo "Excpetion\n";
            if ($queries["dbExxeption"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $databaseClass->query($queries["qException"]);
            $headers      = $queries["dException"];
            $xmlWidth     = $queries["xmlExceptionColumnWidth"];
            $xmlType      = $queries["xmlExceptionColumnType"];
            break;
        case 'R7_Invoice':
            echo "Invoice\n";
            if ($queries["dbInvoice"] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $localDbClass->query($queries["qInvoice"]);
            $headers      = $queries["dInvoice"];
            $xmlWidth     = $queries["xmlInvoiceColumnWidth"];
            $xmlType      = $queries["xmlInvoiceColumnType"];
            break;
        default:
            echo "Ad-hoc report\n";
            $name = $job["type"];

            if ($queries["db" . $name] == "local")
            {
                $localDbClass = $databaseClass;
            }
            else
            {
                $localDbClass = $szopDbClass;
            }
            $localDbClass->query($queries["q" . $name]);
            $headers      = $queries["d" . $name];
            $xmlWidth     = $queries["xml" . $name . "ColumnWidth"];
            $xmlType      = $queries["xml" . $name . "ColumnType"];

            $coreQuery    = false;
            break;
    }

    // Bind the variables to the query
    if ($coreQuery == true)
    {
        $localDbClass->bind(":customer_id", $job["customerId"]);
        $localDbClass->bind(":start_date", $startDate);
        $localDbClass->bind(":end_date",   $endDate);
    }
    else
    {
        foreach($queries["bindVar" . $name] as $key => $field)
        {
            // We know about some fields.
            switch($field)
            {
                case "none":
                    continue 2;
                case "customer_id":
                    $localDbClass->bind(":customer_id", $job["customerId"]);
                    continue 2;
                case "start_date":
                    $localDbClass->bind(":start_date", $startDate);
                    continue 2;
                case "end_date":
                    $localDbClass->bind(":end_date",   $endDate);
                    continue 2;
            }
            // We need to get the value from somewhere, try the query itself.
            $localDbClass->bind(":" . $field,
                    $queries["dataVar" . $name][$key]);
        }
    }

    $localDbClass->execute();
   
    buildInvoiceData($job['customerId']);

    outputData($localDbClass, $job, $headers, $xmlWidth, $xmlType);
    sendData($job);
}

///
// outputData
//
// @param mixed The database table data to be output
// @param mixed The description of the current report
//
function outputData($databaseData, &$job, $headers, $xmlWidth, $xmlType)
{
    global $errorClass;
    global $StartRunTime;
    global $invoiceClass;
    global $sage200FileNameCount;
    $xmlClass = null;
    $csvFile  = null;
    $pdfClass = null;
    // To save the Sage 200 file data into.
    $financeInvoiceFile = null;

    if ($job['type'] == "R7_Invoice")
    {
        $subDirectory = "cir/";
    }
    else
    {
        $subDirectory = "cpr/";
    }

    $fileName = "../output/" . $subDirectory .
                    date("Y-m-d", $StartRunTime) .
                    $job["customerId"];
    $shortFileName = date("Y-m-d", $StartRunTime) .
                    $job["customerId"];

    // Open the Sage 200 file
    if ($job['type'] == "R7_Invoice")
    {

        // Set the invoice file name
        $job["invoiceFileName"]      = $fileName . "_Sage200.csv";
        $job["invoiceShortFileName"] = "postp_transactions_" .
                        date("dmY", $StartRunTime) .
                        sprintf("_%'.04d", $sage200FileNameCount++) .
                        ".csv";

        //die ("Short file name = " . $job["invoiceShortFileName"] . "\n");

        if(($financeInvoiceFile = fopen($fileName . "_Sage200.csv", "wt")) == NULL)
        {
            $errorClass->message("error",
                "Failed to open output file for Sage 200 CSV storage.");
        }
    }

    // We will always save to a CSV file for backup
    if (($csvFile = fopen($fileName . ".csv", "wt")) == null)
    {
        $errorClass->message("error",
            "Failed to open output file for CSV storage.");
    }
    else
    {
        // Output a header row into the data stream
        fputcsv($csvFile, $headers);
    }

    switch ($job["fileFormat"])
    {
        case "CSV":
            // Already taken care of
            $job["fileName"]      = $fileName . ".csv";
            $job["shortFileName"] = $shortFileName . ".csv";
            break;
        case "XML":
            echo "Found XML to prepare\n";
            $job["fileName"]      = $fileName . ".xml";
            $job["shortFileName"] = $shortFileName . ".xml";

            $xmlClass = new ipxmlClass($fileName, $errorClass);

            $xmlClass->setColumnWidth($xmlWidth);
            $xmlClass->setColumnType($xmlType);
            $xmlClass->addNewSheet();

            // Write the description to the XML stream
            $xmlClass->addDataRow($headers, false);
            break;
        case "PDF":
            // TODO put in this code
            $job["fileName"]      = $fileName . ".pdf";
            $job["shortFileName"] = $shortFileName . ".pdf";
            break;
        default:
            break;
    }

    $i = 0;
    $paymentType = $invoiceClass->getCalculationType();

    while($data = $databaseData->resultRow())
    {
        //echo "data = " . print_r($data) . "\n";
        // Get the Postcode for the row and then work out the price for the
        // row
        $pricingCriteria = 'A';
        $weight    = 0.0;
        $surcharge = getParcelSurchargePriceData($data['packcode']);

        switch($paymentType)
        {
            case "SIZE":
                $pricingCriteria = $data['packsize'];
                break;
            case "WEIGHT":
                $pricingCriteria = getParcelWeight($data['packcode']);
                $weight          = $pricingCriteria;
                break;
            case "PERIOD":
            case "SIMPLE":
                $pricingCriteria = getParcelRetentionTime($data['packcode']);
                break;
        }

        // Get the price and add on any postcode zone surcharge fee
        $price = $invoiceClass->processLine($pricingCriteria) +
                    $surcharge;

        // Save the Sage 200 data
        if ($financeInvoiceFile != null)
        {
            outputSage200Data($data,
                    $price,
                    $weight,
                    $financeInvoiceFile,
                    $job["customerId"]);
        }

        if ($csvFile != null)
        {
            $data[] = $price;
            fputcsv($csvFile, $data);
        }
        if ($pdfClass != null)
        {
            $pdfClass->outputLine($data);
        }
        //
        // WARNING - The xml code requires a change for any date time fields.
        // The format HAS to be 2015-01-01T10:16:27
        //
        if ($xmlClass != null)
        {
            if (strlen($data['creation_date']) > 19)
            {
                $data['creation_date'] = substr($data['creation_date'], 0, 19);
            }
            if (strlen($data['original_change_date']) > 19)
            {
                $data['original_change_date'] = substr($data['original_change_date'], 0, 19);
            }
            // The Excel needs a T in position 10
            $data['creation_date'][10]        = "T";
            $data['original_change_date'][10] = "T";

            $xmlClass->addDataRow($data);
        }

        //echo $i++ . " ";
    }

    if ($xmlClass != null)
    {
        $xmlClass->processXMLData();
    }
    if ($csvFile != null)
    {
        fclose($csvFile);
    }
    if ($pdfClass != null)
    {
        $pdfClass->close();
    }
    if ($financeInvoiceFile != null)
    {
        fclose($financeInvoiceFile);
    }
}

///
// sendData
//
// @param mixed The job information has the details of what needs to be sent
//
function sendData($job)
{
    global $errorClass;
    global $clientConfig;

    // Get the job details and then send the data using the appropriate
    // method.
    //echo print_r($clientConfig) . "\n";
    //die("testing...\n");

    if ($clientConfig[$job["key"]]['reporting_mode']['transferMode_Email'] == true)
    {
        // to, from, cc, bcc, message, subject
        $emailClass = new ipemailClass(
                $clientConfig[$job["key"]]["email_details"]["emailTo"],
                "reporting@inpost.co.uk",
                $clientConfig[$job["key"]]["email_details"]["emailCc"],
                $clientConfig[$job["key"]]["email_details"]["emailBcc"],
                "automated report" . $job["customerId"] . " Report type : " .
                    $job["type"],
                "Report Data " . $job["customerId"],
                $errorClass);
        //echo "File name = " . $job["fileName"] . " short " . $job["shortFileName"] . "\n";
        $emailClass->attachment($job["fileName"], $job["shortFileName"]);
        $ret = $emailClass->send();
    }

    if ($clientConfig[$job["key"]]['reporting_mode']['transferMode_FTP'] == true)
    {
        try
        {
            $ftpClass = new ipftpClass(
                $clientConfig[$job["key"]]["ftp_details"]["ftpUname"],
                $clientConfig[$job["key"]]["ftp_details"]["ftpPass"],
                $clientConfig[$job["key"]]["ftp_details"]["ftpHost"],
                $clientConfig[$job["key"]]["ftp_details"]["ftpPort"],
                $clientConfig[$job["key"]]["ftp_details"]["ftpPath"],
                "",
                FTP_ASCII,
                $errorClass
                );
            $ftpClass->connect();
            $ret = $ftpClass->upload($job["fileName"], $job["shortFileName"]);
        }
        catch(Exception $e)
        {
            echo "Exception " . $e . "\n";
        }
    }

    if ($clientConfig[$job["key"]]['reporting_mode']['transferMode_InvoiceFTP'] == true)
    {
        try
        {
            $ftpClass = new ipftpClass(
                $clientConfig[$job["key"]]["invoice_ftp_details"]["ftpUname"],
                $clientConfig[$job["key"]]["invoice_ftp_details"]["ftpPass"],
                $clientConfig[$job["key"]]["invoice_ftp_details"]["ftpHost"],
                $clientConfig[$job["key"]]["invoice_ftp_details"]["ftpPort"],
                $clientConfig[$job["key"]]["invoice_ftp_details"]["ftpPath"],
                "",
                FTP_ASCII,
                $errorClass
                );
            $ftpClass->connect();
            $ret = $ftpClass->upload($job["invoiceFileName"],
                        $job["invoiceShortFileName"]);
        }
        catch(Exception $e)
        {
            echo "Exception " . $e . "\n";
        }
    }
}

///
// checkPostcode
//
// @param string The postcode to be checked
// @brief Check to see if the postcode is an exceptional zone one
// @return float The surcharge based on location of the delivery
//
function checkPostcode($postcode)
{
    global $errorClass;
    global $configData;
    global $queries;

    if ($postcode == "" || strlen($postcode) < 2)
    {
        $errorClass->message("error", "The postcode is not set");
        return false;
    }

    $databaseClass = new ipdatabaseClass("perseus_local",
                                $configData,
                                $errorClass);

    $databaseClass->query($queries["qPostcodeZones"]);
    $databaseClass->bind(":postcode", $postcode);
    $databaseClass->execute();

    $data = $databaseClass->resultRow();

    if (isset($data['zone']))
    {
        // We have found the postcode's zone in the list, find the associated
        // price
        $databaseClass->query($queries["qPostcodePrices"]);
        $databaseClass->bind(":zone", $data['zone']);
        $databaseClass->execute();

        $price = $databaseClass->resultRow();

        return $price['price'];
    }

    // Did not find the postcode, this means we don't have a surcharge to
    // add
    return 0.0;
}

///
// buildInvoiceData
//
// @param string The customer ID to have their invoice type data read
//
function buildInvoiceData($customerId)
{
    global $errorClass;
    global $configData;
    global $queries;
    global $invoiceClass;

    if ($customerId == "" || strlen($customerId) < 2)
    {
        $errorClass->message("error", "Customer ID is empty");
        return false;
    }

    $databaseClass = new ipdatabaseClass("perseus_local",
                                $configData,
                                $errorClass);

    $databaseClass->query($queries["qCustomerPricing"]);
    $databaseClass->bind(":customer_id", $customerId);
    $databaseClass->execute();

    $data = $databaseClass->resultRow();

    //echo "buildInvoiceData data " . print_r($data) . "\n";

    if (!isset($data['price_type']))
    {
        // Nothing was found, set to be size.
        $errorClass->message("debug", "seting default price to be size");
        $data['price_type'] = "size";
    }

    switch ($data['price_type'])
    {
        case "size":
            $databaseClass->query($queries["qCustomerPriceSize"]);
            break;
        case "weight":
            $databaseClass->query($queries["qCustomerPriceWeight"]);
            break;
        case "retention":
            $databaseClass->query($queries["qCustomerPriceRetention"]);
            break;
        case "simple":
            $databaseClass->query($queries["qCustomerPriceSimple"]);
            break;
        default:
            // Should never get here, do nothing.
            break;
    }

    $databaseClass->bind(":customer_id", $customerId);
    $databaseClass->execute();

    switch ($data['price_type'])
    {
        case "size":
            $invoiceClass = new ipinvoiceClass("SIZE", $errorClass);
            $PriceData    = $databaseClass->resultRow();
            
            //echo "Price data " . print_r($PriceData);

            $invoiceClass->setSizeDetails($PriceData);
            break;
        case "weight":
            $invoiceClass = new ipinvoiceClass("WEIGHT", $errorClass);
            $temp = array();
            while ($PriceData    = $databaseClass->resultRow())
            {
                //echo "Price data " . print_r($PriceData) . "\n";
                $temp[] = $PriceData;
            }
            $invoiceClass->setWeightDetails($temp);
            break;
        case "retention":
            $invoiceClass = new ipinvoiceClass("PERIOD", $errorClass);
            $temp = array();
            while ($PriceData    = $databaseClass->resultRow())
            {
                //echo "Price data " . print_r($PriceData) . "\n";
                $temp[] = $PriceData['band_start'];
                $temp[] = $PriceData['band_end'];
                $temp[] = $PriceData['price'];
            }
            $invoiceClass->setRetention($temp);
            break;
        case "simple":
            $invoiceClass = new ipinvoiceClass("SIMPLE", $errorClass);

            $PriceData    = $databaseClass->resultRow();
            $invoiceClass->setSimpleRetentionSurcharge(
                $PriceData['rp_hours'],
                $PriceData['rp_standard'],
                $PriceData['rp_surcharge']);
            break;
    }

    //echo print_r($invoiceClass);
    //die;
}

///
// getParcelSurchargePriceData
//
// @param string The consignment number
//
function getParcelSurchargePriceData($consignment_no)
{
    global $errorClass;
    global $configData;
    global $queries;

    $databaseClass = new ipdatabaseClass("perseus_local",
                                $configData,
                                $errorClass);

    $databaseClass->query($queries["qConsignmentPostcode"]);
    $databaseClass->bind(":consignment_no", $consignment_no);
    $databaseClass->execute();
    $PostcodeData = $databaseClass->resultRow();

    $PostcodeData = explode(" ", $PostcodeData['d_postcode']);

    return(checkPostcode($PostcodeData[0]));
}

///
// getParcelWeight
//
// @param string The consignment number
//
function getParcelWeight($consignment_no)
{
    global $configData;
    global $errorClass;
    global $queries;

    $databaseClass = new ipdatabaseClass("perseus_local",
                                $configData,
                                $errorClass);

    $databaseClass->query($queries["qConsignmentWeight"]);
    $databaseClass->bind(":consignment_no", $consignment_no);
    $databaseClass->execute();
    $weight = $databaseClass->resultRow();

    return($weight['weight']);
}

///
// getParcelRetentionTime
//
// @param string The consignment number
//
function getParcelRetentionTime($consignment_no)
{
    global $errorClass;
    global $configData;
    global $queries;

    $szopDbClass = new ipdatabaseClass("db_00",
                                $configData,
                                $errorClass);

    $szopDbClass->query($queries["qRetentionTime"]);
    $szopDbClass->bind(":consignment_no", $consignment_no);
    $szopDbClass->execute();
    $data = $szopDbClass->resultRow();

    //echo "data found = " . print_r($data) . "\n";

    if (!is_array($data))
    {
        // Failed to find anything.
        $errorClass->message("error", "Failed to find any time information about $consignment_no");
        return 0.0;
    }

   	$stored_date    = strtotime($data["stored"]);
	$delivered_date = strtotime($data["sent"]);

    // Convert into hours the difference
    $diff = ($delivered_date - $stored_date) / 3600;

    return $diff;
}

///
// outputSage200Data
//
// @param mixed  The row data
// @param string The price for the line
//
function outputSage200Data($sourceData,
                $price,
                $weight,
                $sage200File,
                $customerID)
{
    global $errorClass;
    global $configData;
    global $queries;

    $szopDbClass = new ipdatabaseClass("db_00",
                                $configData,
                                $errorClass);

    $szopDbClass->query($queries["qFinanceInvoiceData"]);
    $szopDbClass->bind(":consignment_no", $sourceData['packcode']);
    $szopDbClass->bind(":customer_id", $customerID);
    $szopDbClass->execute();
    $data = $szopDbClass->resultRow();

    //echo "data found = " . print_r($data) . "\n";

    // We have to translate SOME of the customer IDs to be the actual ones
    // found in the Sage 200 system.
    switch ($customerID)
    {
        case "UK10089":
            $customerID = "ASOSCOML";
            break;
        default:
            break;
    }

    fputs($sage200File,'"' . $data['name'] . '","' . $data['packcode'] . '",' .
		'"' . $szopDbClass->changeDate($data['change_date']) . '",' .
        '"' . $customerID . '",' .
		'"' . $data['email'] . '","' . $data['packsize'] . '",' .
		'"' . $price . '","' . $weight . '",' .
		'"' . $data['customer_ref'] . '"' .
		"\n");
}

//-------------------------- end of functions --------------------------------

// Chnge to the local directory so that relative paths will work.
chdir(__DIR__);

$databaseClass = null;
$errorClass    = null;
$emailClass    = null;
// Store the various customers invoice requirements using an array sequenced
// on the cusromer ID. E.g. "UK1534" => invoice object
$invoiceClass  = array();
$szopDbClass   = null;
// The end extension number for the Sage 200 file name
$sage200FileNameCount = 1;

$jobList       = array();
$queries       = array();

echo "Start run " . date("Y-m-d H:i:s") . "\n";

// Save the time that we start so that all of the queries are run from the
// same point.
$StartRunTime = time();

require "../lib/databaseClass.php";
require "../lib/emailClass.php";
require "../lib/errorClass.php";
require "../lib/ftpClass.php";
require "../lib/invoiceClass.php";
require "../lib/xmlClass.php";

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

// Read the query files as well
$queries = parse_ini_file("../queries/reporting/reporting.ini");

if ($queries === false)
{
    $errorClass->message("error", "Failed to read reporting queries file.");
    die("Failed to read reporting quieries file.");
}

$invoicing_queries = parse_ini_file("../queries/invoicing/invoicing.ini");

if ($invoicing_queries === false)
{
    $errorClass->message("error", "Failed to read invoicing queries file.");
    die("Failed to read invoicing quieries file.");
}

$queries = array_merge($queries, $invoicing_queries);

//echo "Queries from the file.\n";
//echo print_r($queries);
//die ("More testing.\n");

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

try
{
    $szopDbClass = new ipdatabaseClass("db_00",
                                $configData,
                                $errorClass);
}

catch(Exception $e)
{
    echo "Failed to open szop database connection, " . $e . "\n";
    die;
}

// Read the client config file in
$dataFiles = regularSearch("../config/clients" , "/^.*\.(ini)$/");

$clientConfig = array();

foreach ($dataFiles as $row)
{
    $clientConfig[] = parse_ini_file($row, true);
}

//echo "client Data = " . print_r($clientConfig) . "\n";
foreach ($clientConfig as $key => $clientData)
{
    if (count($clientData) >= 6)
    {
        $ret = processClientConfig($clientData, $key);
    }
    else
    {
        $errorClass->message("error",
            "Client data file " . $dataFiles[$key] . 
            " does not contain enough entries");
    }
}

// We can process more than one config file and hence the last one might not
// have a report scheduled but the previous config could have, hence count
// the number of jobs setup.
if ($ret || count($jobList) > 0)
{
    // We have some reports to generate, schedule them
    scheduleReports();

    $errorClass->message("debug", "Found some client data to process.");
    //echo "job list " . print_r($jobList) . "\n";
}
else
{
    $errorClass->message("debug", "No client data found to process.");
    printf("No client data found to process.\n");
}

echo "End   run " . date("Y-m-d H:i:s") . "\n";

