<?php
// This class is a simple email one.

///
// inpost_email
//
// @brief Allow a user to send an email with one attachment.
//
class inpost_email
{
	private $from       = "";
	private $to         = "";
	private $cc         = "";
	private $bcc        = "";
	private $attachment = array();
	private $att_name   = array();
	private $file       = NULL;
	private $subject    = "";
	private $message    = "";

	///
	// simple_header
	//
	// @brief Add together the address details for the message.
	//
	private function simple_header()
	{
		# Define the main headers.
		if(strlen($this->from) == 0)
		{
			$header = "From:reporting_inpost.co.uk@robo.integer.pl\r\n";
		}
		else
		{
			$header = "From:$this->from\r\n";
		}
		if(strlen($this->cc) != 0)
		{
			$header .= "Cc: $this->cc\r\n";
		}
		if(strlen($this->bcc) != 0)
		{
			$header .= "Bcc: $this->bcc\r\n";
		}

		return $header;
	}

	///
	// add_attachment
	//
	// @return If successful the header for send or false on an error.
	//
	private function add_attachment()
	{
		# Get a random 32 bit number using time() as seed.
		$num = md5( time() );

		$header = $this->simple_header();

		$header .= "MIME-Version: 1.0\r\n";
		$header .= "Content-Type: multipart/mixed; ";
		$header .= "boundary=$num\r\n";
		$header .= "--$num\r\n";

		# Define the message section
		$header .= "Content-Type: text/plain\r\n";
		$header .= "Content-Transfer-Encoding:8bit\r\n\n";
		$header .= "$this->message\r\n";
		$header .= "--$num\r\n";

		$count = count($this->attachment);

		foreach($this->attachment as $key => $value)
		{
			//echo "key = " . $key . " value = " . $value . "\n";
			//die;
			// Open the file
			if(($this->file = fopen($value, "rt")) == NULL)
			{
				error_log("Error in opening file," . $value);
				return false;
			}

			# Read the file into a variable
			$size    = filesize($value);
			$content = fread($this->file, $size);

			# encode the data for safe transit
			# and insert \r\n after every 76 chars.
			$encoded_content = chunk_split( base64_encode($content));

			# Define the attachment section
			$header .= "Content-Type:  multipart/mixed; ";
			$header .= "name=\"" . $this->att_name[$key] . "\"\r\n";
			$header .= "Content-Transfer-Encoding:base64\r\n";
			$header .= "Content-Disposition:attachment; ";
			$header .= "filename=\"" . $this->att_name[$key] . "\"\r\n\n";
			$header .= "$encoded_content\r\n";

			if($key == ($count-1))
			{
				$header .= "--$num--\r\n";
			}
			else
			{
				$header .= "--$num\r\n";
			}
		}

		return $header;
	}

	///
	// __construct
	//
	public function __construct($to, $from, $cc, $bcc, $message,
				$subject)
	{
		$this->to($to);
		$this->from($from);
		$this->cc($cc);
		$this->bcc($bcc);
		$this->message($message);
		$this->subject($subject);

		return true;
	}

	///
	// from
	//
	// @param The email address to set as the from field.
	//
	public function from($email)
	{
		if(strlen($email) > 4)
		{
			$this->from = $email;
		}
	}

	///
	// to
	//
	// @param The email address to set as the to field.
	//
	public function to($email)
	{
		if(strlen($email) > 4)
		{
			$this->to = $email;
		}
	}

	///
	// cc
	//
	// @param The email address to set as the cc field.
	//
	public function cc($email)
	{
		if(strlen($email) > 4)
		{
			$this->cc = $email;
		}
	}

	///
	// bcc
	//
	// @param The email address to set as the bcc field.
	//
	public function bcc($email)
	{
		if(strlen($email) > 4)
		{
			$this->bcc = $email;
		}
	}

	///
	// attachment
	//
	// @param The filename of the attachment.
	// @param The filename to show in the message.
	//
	public function attachment($filename, $shortFilename)
	{
		if(file_exists($filename))
		{
			$this->attachment[] = $filename;

			if(strlen($shortFilename) > 0)
			{
				$this->att_name[] = $shortFilename;
			}
			else
			{
				$this->att_name[] = $filename;
			}
		}
	}

	///
	// subject
	//
	// @param The subject line for the message.
	//
	public function subject($subject)
	{
		if(strlen($subject) > 2)
		{
			$this->subject = $subject;
		}
	}

	///
	// message
	//
	// @param The message line(s) for the message.
	//
	public function message($message)
	{
		if(strlen($message) > 2)
		{
			$this->message = wordwrap($message, 70, "\r\n");
		}
	}

	///
	// send
	//
	public function send()
	{
		$header = false;
		$retval = false;

		if($this->to == "" && $this->cc == "" && $this->bcc == "")
		{
			// No one set to receive the message
			error_log("No recipients set to get the message.");
			return false;
		}

		// Check to see if we need to attach a file.
		if(count($this->attachment) != 0)
		{
			$header = $this->add_attachment();

		}
		else
		{
			$header = $this->simple_header();
			if($header !== false)
			{
				$header .= $this->message . "\r\n";
			}
		}

		if($header !== false)
		{
			$retval = mail($this->to,
				$this->subject, "",
				$header);
		}

		if($this->file != NULL)
		{
			fclose($this->file);
		}

		return $retval;
	}
}
