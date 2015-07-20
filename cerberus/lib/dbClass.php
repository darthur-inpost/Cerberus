<?php 
class dbClass
{
    private $conn;
    public  $dbName;
    private $dsn;
    private $dbid;
    private $stmt;
    private $retVal;
    private $errDesc;
    private $logPath;

	///
	// __construct
	//
	// @param The database connection to try and open
	// @param The error logging directory
	// @param The config data
	//
	public function __construct($dbid, $path, $config = NULL)
	{
		if($config == NULL)
		{
			$config = parse_ini_file('../config/config.ini', TRUE);

			if($config_data === false)
			{
				error_message("Failed to open config file.", "email",
					'integration@inpost.co.uk');
				die;
			}
		}

		if (!isset($this->conn))
		{
			try{
				$this->dbName  = $config[$dbid]['dbname'];
				$this->logPath = $path;
				$this->dsn     = $config[$dbid]['dbtype'].":host=".$config[$dbid]['dbhost'].";dbname=".$config[$dbid]['dbname']; 
				$this->conn    = new PDO($this->dsn, $config[$dbid]['username'], $config[$dbid]['password']);

				$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
			}

			catch(PDOException $e){
				//log error
				$this->errDesc= date("Y-m-d H:i:s")." - Object creation failed. Details: " .$e ."\n";
				error_log($this->errDesc, 3, $this->logPath); 
				//print_r($this->errDesc);
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
			error_log($this->errDesc, 3, $this->logPath); 
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
            return false;
        }

        $this->retVal=$this->stmt->bindValue($param, $value, $type);

        if(!$this->retVal)
        {
            //echo "Failed\n";

            $this->errDesc= date("Y-m-d H:i:s")." - Failed to bind values: " .
                json_encode($this->conn->errorInfo()) ."\n";
            //print_r($this->errDesc);
            error_log($this->errDesc, 3, $this->logPath);
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
	$retVal = 0;

        try
        {
            $retVal = $this->stmt->execute();
        }
        catch(exception $e)
        {
            echo "exception " . print_r($e) . "\n";
            if (!$retVal)
            {
                $this->errDesc= date("Y-m-d H:i:s")." Execution failure: " .json_encode($this->conn->errorInfo()) ."\n";
                print_r($this->errDesc);
                error_log($this->errDesc, 3, $this->logPath);
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
    public function resultset()
    {
        $retVal = $this->execute();
        if (!$retVal)
        {
            return FALSE;
        }
        return $this->stmt->fetchAll(); 
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
}
