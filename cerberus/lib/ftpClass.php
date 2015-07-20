<?php
// This class provides a simple FTP class

class ipftpClass
{
	private $username = "";
	private $password = "";
	private $host     = "";
	private $port     = "21";
	private $local    = "";
	private $remote   = "";
	private $mode     = FTP_ASCII;
	private $error    = null;

	private $ftp;
	private $login;

    ///
    // __construct
    //
	// @param The user name to connect with.
	// @param The password to connect with.
	// @param The host to connect to.
	// @param The port to connect to.
	// @param The remote directory to use
	// @param The local directory to use
	// @param The file transfer mode to use
    //
    public function __construct(
                        $username, $password, $host, $port, $remote, $local,
                        $mode, $error)
    {
        $this->error = $error;

        $this->username($username);
        $this->password($password);
        $this->host($host);
        $this->port($port);
        $this->remote($remote);
        $this->local($local);
        $ret = $this->mode($mode);

        if($ret == false)
        {
            $error->message("ERROR", "Incorrect mode selected.");
            throw new Exception("Incorrect mode selected");
        }

        return true;
    }

	///
	// username
	//
	// @param The user name to connect with.
	//
	public function username($username)
	{
		if(strlen($username) > 2)
		{
			$this->username = $username;
		}
	}

	///
	// password
	//
	// @param The password to connect with.
	//
	public function password($pass)
	{
		if(strlen($pass) > 2)
		{
			$this->password = $pass;
		}
	}

	///
	// host
	//
	// @param The host to connect to.
	//
	public function host($host)
	{
		if(strlen($host) > 2)
		{
			$this->host = $host;
		}
	}

	///
	// port
	//
	// @param The port to connect to.
	//
	public function port($port)
	{
		if(strlen($port) >= 1)
		{
			$this->port = $port;
		}
	}

	///
	// local
	//
	// @param The local directory to get / download files to.
	//
	public function local($local)
	{
		if(strlen($local) >= 1)
		{
			$this->local = $local;
		}
	}

	///
	// remote
	//
	// @param The directory on the remote server to change to.
	//
	public function remote($remote)
	{
		if(strlen($remote) >= 1)
		{
			$this->remote = $remote;
		}
	}

	///
	// mode
	//
	// @param The type of transfer to use.
	// @return True if mode changed, otherwise false.
	//
	public function mode($mode)
	{
		switch($mode)
		{
			case "FTP_ASCII":
			case FTP_ASCII:
				$this->mode = FTP_ASCII;
				break;
			case "FTP_BINARY":
			case FTP_BINARY:
				$this->mode = FTP_BINARY;
				break;
			default:
				$this->error->message("DEBUG", "Incorrect mode selected.");
				return false;
		}

		return true;
	}

	///
	// __destruct
	//
	// @Brief Free any used resources.
	//
	public function __destruct()
	{
		if($this->ftp != NULL)
		{
			ftp_close($this->ftp);
			$this->ftp = NULL;
		}
	}

	///
	// connect
	//
	// @brief Establish the connection to the remote server and change to the correct directory.
	//
	// @return True if connected successfully or false.
	//
	public function connect()
	{
		$this->ftp   = ftp_connect($this->host, $this->port);

		$this->login = ftp_login($this->ftp, $this->username,
				$this->password);

		if(!$this->ftp || !$this->login)
		{
			error_log("Failed to connect to FTP host " . $this->host);

			throw new Exception("Failed to connect to FTP host");
		}

		// Switch on passive mode.
		$return = ftp_pasv($this->ftp, true);

		if(strlen($this->remote) > 0)
		{
			$ret = ftp_chdir($this->ftp, $this->remote);

			if(!$ret)
			{
				error_log("Failed to change to the correct directory.");
				throw new Exception("Failed to change to the correct directory");
			}
		}

		return true;
	}

	///
	// upload
	//
	// @brief Upload a file to the remote server.
	// @param The file to upload
	// @param The remote filename
	//
	public function upload($filename, $remoteFilename)
	{
		if (strlen(trim($filename)) == 0 ||
            strlen(trim($remoteFilename)) == 0)
		{
			$this->error->message("ERROR", "No file name specified.");
			throw new Exception("No file name specified");
		}

        /*
        $curl = curl_init();
        $file = fopen($filename, "r");
        if ($file == null)
        {
            echo "File handle failed to open.\n";
            return false;
        }

        curl_setopt($curl, CURLOPT_URL,
            "ftp://ftp.inpost247.net/" .
            $remoteFilename);

 	curl_setopt($curl, CURLOPT_UPLOAD, 1);
 	curl_setopt($curl, CURLOPT_INFILE, $file);
 	curl_setopt($curl, CURLOPT_INFILESIZE, filesize($filename));
        curl_setopt($curl, CURLOPT_USERPWD, "$this->username:$this->password");
        //curl_setopt($curl, CURLOPT_URL, "ftp://ftp.inpost247.net/");
                        //"Inpost/" . $remoteFilename);
                        //$remoteFilename);
        //curl_setopt($curl, CURLOPT_UPLOAD, 1);
        //curl_setopt($curl, CURLOPT_INFILE, $file);
        //curl_setopt($curl, CURLOPT_INFILESIZE, filesize($filename));
        //curl_setopt($curl, CURLOPT_USERPWD, "$this->username:$this->password");
        //curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        //curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        //curl_setopt($curl, CURLOPT_FTPPORT, 21);
        //curl_setopt($curl, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_SSL);

        curl_exec($curl);

        $error_no = curl_errno($curl);
        $error_tx = curl_error($curl);

        curl_close($curl);
        fclose($file);

        if ($error_no == 0)
        {
            echo "Upload seemed to work.\n";
            return true;
        }
        else
        {
            echo "Upload failed to work. $error_no.\n";
            echo "Upload failed to work. $error_tx.\n";
            return false;
        }

        return;
        */
		$ret = ftp_put($this->ftp,
			$remoteFilename,
			$this->local . $filename,
			$this->mode);

		if (!$ret)
		{
			$this->error->message("ERROR",
                    "Failed to upload file $this->local$filename");
			throw new Exception("Failed to upload file $filename");
		}

		return true;
	}

	///
	// download
	//
	// @brief Upload a file to the remote server.
	// @param The file to upload
	// @param The remote filename
	//
	public function download($filename, $remoteFilename)
	{
		if (strlen(trim($filename)) == 0 ||
            strlen(trim($remoteFilename)) == 0)
		{
			$this->error->message("ERROR", "No file name specified.");
			throw new Exception("No file name specified");
		}

		$ret = ftp_get($this->ftp,
			$this->local . $filename,
			$remoteFilename,
			$this->mode);

		if(!$ret)
		{
			error_log("Failed to download file $remoteFilename");
			throw new Exception("Failed to download file $remoteFilename");
		}

		return true;
	}
}
