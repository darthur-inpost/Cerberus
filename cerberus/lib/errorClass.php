<?php
///
// errorClass

//----------------------------------------------------------------------------
// File and Directory Modes
//----------------------------------------------------------------------------
define("FILE_READ_MODE",  0644);
define("FILE_WRITE_MODE", 0666);
define("DIR_READ_MODE",   0755);
define("DIR_WRITE_MODE",  0777);

//----------------------------------------------------------------------------
// File Stream Modes
//----------------------------------------------------------------------------
define("FOPEN_READ",                          "rb");
define("FOPEN_READ_WRITE",                    "r+b");
define("FOPEN_WRITE_CREATE_DESTRUCTIVE",      "wb");
define("FOPEN_READ_WRITE_CREATE_DESTRUCTIVE", "w+b");
define("FOPEN_WRITE_CREATE",                  "ab");
define("FOPEN_READ_WRITE_CREATE",             "a+b");
define("FOPEN_WRITE_CREATE_STRICT",           "xb");
define("FOPEN_READ_WRITE_CREATE_STRICT",      "x+b");

class iperrorClass
{
    private $severity      = 1;
    private $directory     = "../logs/";
    private $fileName      = "log-";
    private $email         = "integration@inpost.co.uk";
    private $dateFormat    = "Y-m-d H:i:s";
    private $enabled       = true;
    private $levelForEmail = 1;
    private $levels        = array("ERROR" => "1", "DEBUG" => "2",
                             "INFO" => "3",
                             "ALL"  => "4",
                             );

    ///
    // __construct
    //
    // @param The level beyond which messages will be output
    // @param The directory where the log file will be
    // @param The email address(es) where messages will be sent.
    // @param The level at which emails will be sent to people
    //
    public function __construct($severity, $directory, $fileName,
        $email, $levelForEmail)
    {
        if (is_numeric($severity))
        {
            $this->severity  = $severity;
        }

        if (strlen($directory) > 0)
        {
            // Check to see if the directory terminates in a slash.
            if($directory[strlen($directory) - 1] != '/')
            {
                $directory .= '/';
            }
            $this->directory = $directory;
        }

        if (!is_dir($this->directory))
        {
            // We can't write to the log directory
            $this->enabled = false;
            echo "Can't access directory $this->directory\n";
        }

        if (strlen($fileName) > 0)
        {
            $this->fileName  = $fileName;
        }
        $this->email         = $email;
        if (is_numeric($levelForEmail))
        {
            $this->levelForEmail = $levelForEmail;
        }
    }

    ///
    // email
    //
    // @param string Email address for error messages
    //
    public function email($email)
    {
        if (strlen($email) > 2)
        {
            $this->email = $email;
        }
    }

    ///
    // message
    //
    // @param  string The level of the message
    // @param  string The actual message to be output
    // @return bool
    //
    public function message($level, $mess)
    {
        if ($this->enabled == false)
        {
            return false;
        }

        $level = strtoupper($level);

        if (!isset($this->levels[$level]) ||
            $this->levels[$level] > $this->severity)
        {
            return false;
        }

        // Check if we need to email the message.
        if ($level >= $this->levelForEmail && $this->email != "")
        {
            $ret = mail($this->email, "An error has occured", $mess);

            if (!$ret)
            {
                echo "Failed to send email.\n";
            }
        }

        $filePath = $this->directory.$this->fileName.date("Y-m-d").".txt";

        if (!$fp = fopen($filePath, FOPEN_WRITE_CREATE))
        {
            echo "Failed to open log file $filePath for writing.\n";
            return false;
        }

        $message = $level.' '.(($level == 'INFO') ? ' -' : '-').' '.
                    date($this->dateFormat)." --> ".$mess."\n";

        flock($fp, LOCK_EX);
        $ret = fwrite($fp, $message);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($ret === false)
        {
            echo "Failed to write to log file, $filePath.\n";
            return false;
        }

        // Don't output any errors if this fails.
        @chmod($filePath, FILE_WRITE_MODE);
        return true;
    }
}
