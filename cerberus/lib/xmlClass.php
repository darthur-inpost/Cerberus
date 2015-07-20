<?php
//
// Build the XML report.
//
class ipxmlClass
{
	private $xml            = null;
	private $xmlFileName    = "";
    private $sheetNumber    = 0;
    private $sheetName      = array();
    private $columnTypes    = array();
    private $columnWidths   = array();
    private $tempFileHandle = null;
    private $error          = null;
    private $rowCount       = array();
    private $rowColumnCount = 0;

    ///
    // __construct
    //
    // @brief setup the XML class
    // @param pointer To the error class
    //
    public function __construct($fileName = "", $error)
    {
        // TODO change this back
        //$this->tempFileHandle = tmpfile();
        
        $this->tempFileHandle = fopen("data_stream.tmp", "w+t");

        if ($fileName != "" && strlen($fileName) > 2)
        {
            $this->xmlFileName = $fileName;
        }

        if ($error != null)
        {
            $this->error = $error;
        }
        else
        {
            throw new InvalidArgumentException(
                "Error class pointer must not be null");
        }

        return true;
    }

	///
	// __destruct
	//
	// @brief remove the memory reference to the XML local variable
	//
	function __destruct()
	{
		unset($this->xml);
        $this->xml = null;

        fclose($this->tempFileHandle);
        $this->tempFileHandle = null;
	}

    ///
    // setColumnWidth
    //
    // @param mixed The widths for each column
    // @brief Define each of the columns widths
    //
    public function setColumnWidth($columns)
    {
        foreach ($columns as $column)
        {
            if (is_int($column) || is_float($column))
            {
                $this->columnWidths[] = $column;
            }
            else
            {
                $this->error->message("error",
                        "Type $column width not recognised type");
                throw new InvalidArgumentException(
                        "Type $column wdith not recognised type");
            }
        }

        // Check to see if the number of columns accross the sheet is higher
        // than the count we have now.
        if (count($this->columnWidths) > $this->rowColumnCount)
        {
            $this->rowColumnCount = count($this->columnWidths);
        }

        return true;
    }

    ///
    // setColumnType
    //
    // @param mixed The types for each column
    // @brief Define each of the columns types
    //
    public function setColumnType($columns)
    {
        foreach ($columns as $column)
        {
            $column = strtoupper($column);

            switch($column)
            {
                case "STRING":
                    $this->columnTypes[] = "String";
                    break;
                case "NUMBER":
                    $this->columnTypes[] = "Number";
                    break;
                case "DATETIME":
                    $this->columnTypes[] = "DateTime";
                    break;
                default:
                    $this->error->message("error",
                        "Type $column not recognised");
                    throw new InvalidArgumentException(
                        "Type $column not recognised");
                    break;
            }
        }

        return true;
    }

    ///
    // addNewSheet
    //
    // @param string The name of the sheet
    //
    public function addNewSheet($sheetName = "")
    {
        if ($sheetName == "" || strlen(trim($sheetName)) == 0)
        {
            $this->error->message("debug",
                "Sheet name should be longer than zero characters.");
            $sheetName = "Sheet";
        }

        $this->sheetName[$this->sheetNumber++] = $sheetName;

        return true;
    }

    ///
    // addDataRow
    //
    // @param array The data to be put into the row
    // @param boolean True for a data row, false for a header row
    //
    public function addDataRow($data, $rowType = true)
    {
        if ($this->sheetNumber == 0)
        {
            $this->error->message("error",
                "Please add a sheet to the XML feed first.");
            throw new Exception(
                "Please add a sheet to the XML feed first.");
        }

        // Assume we are writing a row
        $dataRow = true;

        if (!$rowType)
        {
            $dataRow = false;
        }

        // Increment the row count for the current sheet
        // NB because of dynamic array allocation we have to do that first or
        // the array allocation fails.
        if (!isset($this->rowCount[$this->sheetNumber - 1]))
        {
            $this->rowCount[$this->sheetNumber - 1] = 1;
        }
        else
        {
            $this->rowCount[$this->sheetNumber - 1]++;
        }

        // Output the start of the temp file data
        fwrite($this->tempFileHandle, "\"$this->sheetNumber\",\"" .
            sprintf("%01d", $dataRow) . "\"");

        foreach($data as $key => $row)
        {
            $saveData = htmlspecialchars(addslashes(trim(str_replace("\n", "", $row))));

            fwrite($this->tempFileHandle, ",\"$saveData\"");
        }

        // Output a CR to end the row
        fwrite($this->tempFileHandle, "\n");

        // Check to see if the number of columns accross the sheet is higher
        // than the count we have now.
        // NB $key is based from zero hence we add one to it.
        if ($key + 1 > $this->rowColumnCount)
        {
            $this->rowColumnCount = $key + 1;
        }

        return true;
    }

    ///
    // processXMLData
    //
    // @brief Build the full XML data and return it
    //
    public function processXMLData()
    {
        if (count($this->sheetName) == 0)
        {
            $this->error->message("error",
                "Please add some sheets before trying to output the XML");
            throw new exception(
                "Please add some sheets before trying to output the XML");
        }

        // Rewind to the begining of the data
        rewind($this->tempFileHandle);

        $this->startXML();
        foreach($this->sheetName as $key => $sheet)
        {
            $this->startWorkSheet($sheet,
                $this->rowCount[$key],
                $this->rowColumnCount);

            $this->writeColumns($this->columnWidths);
            for ($i = 0; $i < $this->rowCount[$key]; $i++)
            {
                // Get only one line of data from the file.
                $data = fgetcsv($this->tempFileHandle);

                // Check that the data row matches the sheet number.
                if ($data[0] != $key + 1)
                {
                    $this->error->message("debug",
                        "data sheet number does not match current sheet");
                    continue;
                }

                $this->writeRow($data);
            }
            $this->closeWorkSheet();
        }
        return $this->closeXML();
    }

    //-------------------- Private Methods -----------------------------------

	///
	// writeRow
	//
	// @brief Write an entire row into the XML stream
	//
	private function writeRow($rowData)
	{
        // The type used for each column type
        $headerRow  = true;
        $columnType = "String";

		if($rowData != NULL && count($rowData) > 0)
		{
            // Determine whether to use column types or if they are String
            if ($rowData[1] == 1)
            {
                $headerRow = false;
            }

			$this->xml->startElement('Row');
			foreach($rowData as $key => $cell_data)
			{
                // Skip the first column as it only holds the sheet number
                // Skip the second column as it simply defines the row type
                if ($key == 0 || $key == 1)
                {
                    continue;
                }
                if (!$headerRow)
                {
                    $columnType = $this->columnTypes[$key - 2];

                    if (!$this->checkDataTypeMatches($columnType, $cell_data))
                    {
                        $this->error->message("error",
                            "Data type mismatch for $cell_data and $columnType");

                        // This makes the sheet useless, return an exception
                        throw new exception(
                            "Data type mismatch for $cell_data and $columnType");
                    }
                }

				//echo "cell = " . $cell_data . "\n";
				$this->xml->writeAttribute("ss:AutoFitHeight", "0");
				$this->xml->startElement('Cell');
                    if ($columnType == "DateTime")
                    {
					    $this->xml->writeAttribute("ss:StyleID", "s62");
                    }
					$this->xml->startElement('Data');
					    $this->xml->writeAttribute("ss:Type", $columnType);
					    $this->xml->text($cell_data);
					$this->xml->endElement();
				$this->xml->endElement();
			}
			$this->xml->endElement();
		}
		else
		{
			$this->error->message("debug",
                "Failed to find any data to process.");
		}
	}

	///
	// writeColumns
	//
	private function writeColumns($columnData)
	{
		if($columnData != NULL && count($columnData) > 0)
		{
			foreach($columnData as $cell_data)
			{
				$this->xml->startElement('Column');
					$this->xml->writeAttribute("ss:Width", $cell_data);
				$this->xml->endElement();
			}
		}
	}

    ///
    // my_xml
    //
    // @param name of the file to be opened, will automatically get .xml added
    //
    private function startXML()
    {
	    $this->xml = new XMLWriter();

        if (!is_object($this->xml))
        {
            $this->error->message("error",
                "Failed to created new XMLWriter object.");
            throw new exception(
                "Failed to created new XMLWriter object.");
        }

        if ($this->xmlFileName != "")
        {
	        $ret = $this->xml->openURI($this->xmlFileName . '.xml');
        }
        else
        {
    	    $ret = $this->xml->openMemory();
        }

        if ($ret === false)
        {
            $this->error->message("error",
                "Failed to open the XML stream for writing into.");
            throw new exception(
                "Failed to open the XML stream for writing into.");
        }

	    $this->xml->setIndent(true);
	    $this->xml->setIndentString('  ');
	    $this->xml->startDocument('1.0');
	    $this->xml->writePi('mso-application', 'progid="Excel.Sheet"');

	    $this->xml->startElement('Workbook');

	    // Start the headers for the whole sheet
	    $this->xml->writeAttribute('xmlns', "urn:schemas-microsoft-com:office:spreadsheet");
	    $this->xml->writeAttribute('xmlns:o', "urn:schemas-microsoft-com:office:office");
	    $this->xml->writeAttribute('xmlns:x', "urn:schemas-microsoft-com:office:excel");
	    $this->xml->writeAttribute('xmlns:ss', "urn:schemas-microsoft-com:office:spreadsheet");
	    $this->xml->writeAttribute('xmlns:html', "http://www.w3.org/TR/REC-html40");

  	    $this->xml->startElement('DocumentProperties');
    		    $this->xml->writeAttribute('xmlns', "urn:schemas-microsoft-com:office:office");
    		    $this->xml->writeElement('Author', 'InPost UK');
    		    $this->xml->writeElement('LastAuthor', 'InPost UK');
    		    $this->xml->writeElement('Created', date('Y-m-d') . 'T' . date('H:i:s') . 'Z');
    		    $this->xml->writeElement('LastSaved', date('Y-m-d') . 'T' . date('H:i:s') . 'Z');
    		    $this->xml->writeElement('Version', '15.00');
  	    $this->xml->endElement();

  	    $this->xml->startElement('OfficeDocumentSettings');
		    $this->xml->writeAttribute('xmlns', "urn:schemas-microsoft-com:office:office");
		    $this->xml->writeElement('AllowPNG');
	    $this->xml->endElement();

	    $this->xml->startElement('ExcelWorkbook');
		    $this->xml->writeAttribute('xmlns', "urn:schemas-microsoft-com:office:excel");
		    $this->xml->writeElement('WindowHeight', '8985');
		    $this->xml->writeElement('WindowWidth', '20490');
		    $this->xml->writeElement('WindowTopX', '0');
		    $this->xml->writeElement('WindowTopY', '0');
		    $this->xml->writeElement('ProtectStructure', 'False');
		    $this->xml->writeElement('ProtectWindows', 'False');
	    $this->xml->endElement();

	    $this->xml->startElement('Styles');
		    $this->xml->startElement('Style');
		        $this->xml->writeAttribute('ss:ID', "Default");
		        $this->xml->writeAttribute('ss:Name', "Normal");
		        $this->xml->startElement('Alignment');
		            $this->xml->writeAttribute('ss:Vertical', "Bottom");
		        $this->xml->endElement();
		        $this->xml->writeElement('Borders');
		        $this->xml->startElement('Font');
		            $this->xml->writeAttribute('ss:FontName', "Calibri");
		            $this->xml->writeAttribute('x:Family', "Swiss");
		            $this->xml->writeAttribute('ss:Size', "11");
		            $this->xml->writeAttribute('ss:Color', "#000000");
		        $this->xml->endElement();
		        $this->xml->writeElement('Interior');
		        $this->xml->writeElement('NumberFormat');
		        $this->xml->writeElement('Protection');
		    $this->xml->endElement();
		    $this->xml->startElement('Style');
		        $this->xml->writeAttribute('ss:ID', "s62");
		        $this->xml->startElement('NumberFormat');
		            $this->xml->writeAttribute('ss:Format', "General Date");
		        $this->xml->endElement();
		    $this->xml->endElement();
	    $this->xml->endElement();

	    // End of the headers for the whole sheet.
    }

    ///
    // startWorkSheet
    //
    // @param The name of the sheet to start.
    //
    private function startWorkSheet($sheetName, $rowCount, $columnCount)
    {
        // Start of the sheet data.
	    $this->xml->startElement('Worksheet');
		    $this->xml->writeAttribute('ss:Name', $sheetName);
		    $this->xml->startElement('Table');
			    $this->xml->writeAttribute("ss:ExpandedColumnCount", 
				    $columnCount);
			    $this->xml->writeAttribute("ss:ExpandedRowCount",
				    $rowCount + 1);
			    $this->xml->writeAttribute("x:FullColumns", "1");
			    $this->xml->writeAttribute("x:FullRows", "1");
			    $this->xml->writeAttribute("ss:DefaultColumnWidth", "54");
			    $this->xml->writeAttribute("ss:DefaultRowHeight", "15");
    }

    ///
    // writeWorkSheetOptions
    //
    private function writeWorkSheetOptions()
    {
	    $this->xml->startElement('WorksheetOptions');
	    $this->xml->writeAttribute("xmlns", "urn:schemas-microsoft-com:office:excel");

	    $this->xml->startElement('PageSetup');
	    $this->xml->startElement('Header');
		    $this->xml->writeAttribute("x:Margin", "0.3");
	    $this->xml->endElement();
	    $this->xml->startElement('Footer');
		    $this->xml->writeAttribute("x:Margin", "0.3");
	    $this->xml->endElement();
		    $this->xml->startElement('PageMargins');
		    $this->xml->writeAttribute("x:Bottom", "0.75");
		    $this->xml->writeAttribute("x:Left", "0.7");
		    $this->xml->writeAttribute("x:Right", "0.7");
		    $this->xml->writeAttribute("x:Top", "0.75");
		    $this->xml->endElement();
	    $this->xml->endElement();

	    $this->xml->startElement('Unsynced');
	    $this->xml->endElement();

	    $this->xml->writeElement('ProtectObjects', 'False');
	    $this->xml->writeElement('ProtectScenarios', 'False');

	    $this->xml->endElement(); // Worksheet options
    }

	///
	// closeWorkSheet
	//
	// @brief End the sheet ready for a new one.
	//
	private function closeWorkSheet()
	{
			$this->xml->endElement(); // Table

			$this->writeWorkSheetOptions();

		$this->xml->endElement(); // Worksheet
	}

	///
	// closeXML
	//
	private function closeXML()
	{
		$this->xml->endDocument();
		return $this->xml->flush();
	}

    ///
    // checkDataTypeMatches
    //
    // @param string The column type
    // @param mixed  The data to be checked
    //
    // NB for checking the Numeric values is_int, is_float, etc. do NOT
    // work. They seem to expect a differently formatted value and a passed
    // parameter fails in ALL caes. So we use is_numeric.
    //
    private function checkDataTypeMatches($columnType, $cellData)
    {
        $return = true;

        switch ($columnType)
        {
            case "String":
                // Anything is allowed.
                break;
            case "Number":
                if (!is_numeric($cellData))
                {
                    $this->error->message("debug", "non-numeric found.");
                    $return = false;
                }
                break;
            case "DateTime":
                if ($cellData[4]  != '-' || $cellData[7]  != '-' ||
                    $cellData[10] != 'T' || $cellData[13] != ':' ||
                    $cellData[16] != ':' || strlen($cellData) != 19)
                {
                    $this->error->message("debug", "non-date found.");
                    $return = false;
                }
                break;
            default:
                $return = false;
                break;
        }

        return $return;
    }
}

/*
 * The following code demonstrates how to build a single sheet.
 * The order of the method calls is crucial. The XML structure built in the
 * file will follow exactly the order you call the methods with.
 *
$my_test = new my_xml("t1");

$my_test->write_sheet("fred2");

	// Add some columns.      
	$my_test->write_column(array("69", "157.5", "61.5", "21", "73.5"));

	// Add some rows.
	$my_test->write_row(array("test awb 1357",
		"gb.lambeth.cardleft@dhl.com",
		"812761799",
		"A",
		"UKLON10187"));
	$my_test->write_row(array("test awb 2468",
		"paul.morrison@dhl.com",
		"812761799",
		"B",
		"UKLON10965"));
	$my_test->write_row(array("test awb 1234",
		"gb.lambeth.cardleft@dhl.com",
		"812761799",
		"C",
		"UKLON11161"));
	$my_test->write_row(array("test awb 5678",
		"paul.morrison@dhl.com",
		"812761799",
		"A",
		"UKLON12939"));

$my_test->write_worksheet_options();

$my_test->close_sheet();

$my_test->end_xml();
 */
