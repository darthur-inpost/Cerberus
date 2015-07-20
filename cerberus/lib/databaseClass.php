<?php 
///
// ipdatabaseClass
//
// NB the PDO class does NOT provide a mechanism for going back to the
// beginning of a data set. If you want to do that you would have to re-exeucte
// the same query then fetch each row.
//
class ipdatabaseClass
{
    private $conn    = null;
    public  $dbName  = "";
    private $dsn     = "";
    private $dbid    = "";
    private $stmt    = "";
    private $retVal  = 0;
    private $errDesc = "";
    private $error   = null;

	///
	// __construct
	//
	// @param string The database connection to try and open
	// @param mixed  The config data
	// @param class  Pointer to the error class
	//
	public function __construct($dbid, $config = NULL, $error = null)
	{
        if ($error == null)
        {
            throw new InvalidArgumentException(
                "error class must be initialised first.");
        }

        $this->error = $error;

		if ($config == NULL)
		{
			$config = parse_ini_file('../config/config.ini', TRUE);

			if($config_data === false)
			{
				$this->error->message("error", "Failed to open config file.");
                throw new exception("Failed to open config file");
			}
		}

		if (!isset($this->conn) || $this->conn == null)
		{
			try
            {
				$this->dbName  = $config[$dbid]['dbname'];
				$this->dsn     = $config[$dbid]['dbtype'].":host=".$config[$dbid]['dbhost'].";dbname=".$config[$dbid]['dbname']; 
				$this->conn    = new PDO($this->dsn, $config[$dbid]['username'], $config[$dbid]['password']);

				$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
			}

			catch(PDOException $e)
            {
				//log error
				$this->errDesc= date("Y-m-d H:i:s")." - Object creation failed. Details: " .$e ."\n";
				$this->error->message("error", $this->errDesc); 

				return FALSE; 
			}

			return $this->conn;
		}
	}

    ///
    // __destruct
    //
    // @brief Release the db connection by setting it to null
    //
	public function __destruct()
	{
		$this->conn = null;
	}

    ///
    // query
    //
    // @param string The query to be executed
    //
	public function query($query)
	{
		$this->stmt = $this->conn->prepare($query);
                     
		if (!($this->stmt))
		{
			$this->errDesc= date("Y-m-d H:i:s")." Failed to prepare query: " .$this->conn->errorInfo() ."\n";
			//print_r($this->errDesc);
			$this->error->message("error", $this->errDesc);
			return FALSE;
		}

		return TRUE;
	}

    ///
    // bind
    //
    // @param string The parameter to be bound to
    // @param mixed  The value to be used in the query
    // @param PDO    The actual type that the value should be bound as
    //
    public function bind($param, $value, $type = null)
    {
        if (is_null($type))
        {
            switch (true)
            {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
                    break;
            }
        }
        else if ($type != PDO::PARAM_INT  && $type != PDO::PARAM_BOOL &&
                 $type != PDO::PARAM_NULL && $type != PDO::PARAM_STR)
        {
            // Type parameter is not correct
            $this->error->message("error", "Type for bind was not correct " .
                $type);
            return false;
        }

        $this->retVal=$this->stmt->bindValue($param, $value, $type);

        if(!$this->retVal)
        {
            //echo "Failed\n";

            $this->errDesc= date("Y-m-d H:i:s")." - Failed to bind values: " .
                json_encode($this->conn->errorInfo()) ."\n";
            //print_r($this->errDesc);
            $this->error->message("error", $this->errDesc);
            return FALSE;
        }                        
        //return TRUE;
        return $this->retVal;
    }

    ///
    // execute
    //
    public function execute()
    {
        try
        {
            $retVal = $this->stmt->execute();
        }
        catch(exception $e)
        {
            echo "exception " . print_r($e) . "\n";
            if (!$retVal)
            {
                $this->errDesc= date("Y-m-d H:i:s")." Execution failure: " .$this->conn->errorInfo() ."\n";
                //print_r($this->errDesc);
                $this->error->message("error", $this->errDesc);
                return FALSE;
            }
        }
        return $retVal;
    }

    ///
    // resultSet
    //
    // @brief Return all the data from the last query
    //
    public function resultSet()
    {
        $retVal = $this->execute();
        if (!$retVal)
        {
            $this->error->message("debug",
                "No result set ready for some reason.");
            return FALSE;
        }
        return $this->stmt->fetchAll(); 
    }

    ///
    // resultRow
    //
    // @brief Return a row of the data from the last query
    //
    public function resultRow()
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC); 
    }

    ///
    // rowCount
    //
    // @brief Return the row count from the query
    //
    public function rowCount()
    {
        //echo "in rowCount<br>";
        return $this->stmt->rowCount();
    }

    ///
    // changeDate
    //
    // @param  string The date to be converted
    // @return string The date as a UK format one
    //
    public function changeDate($date)
    {
        $ret = substr($date, 8, 2);
        $ret .= "/";
        $ret .= substr($date, 5, 2);
        $ret .= "/";
        $ret .= substr($date, 0, 4);

        return $ret;
    }

}
