<?php
// A set of utility functions for us.

///
// error_message
//
// @param The message to be processed
// @param The output type for the message
// @param The email recipients for the message
//
// @return None
//
function error_message($mess, $type='log', $recipients)
{
	switch($type)
	{
		case 'email':
			$ret = mail($recipients,
				"log message " . date("Y:d:m H:i:s"),
				$mess);

			if(!$ret)
			{
				error_log("Failed to send email.");
			}
		case 'log':
		default:
			file_put_contents('../logs/error_log.txt',
				date("Y-m-d H:i:s :") . $mess . "\n",
				FILE_APPEND);
			break;
	}
}

///
// send_email
//
// @brief Send a file to the list of email recipients
// @param filename to be opened
// @param short filename to be put into the message
// @param email of recipient
// @param manager recipient email addresses
// @param message
//
function send_email($filename, $short_filename, $email_address, $message, $subject)
{
	// Open a file
	if(($file = fopen($filename, "rt")) == NULL)
	{
		error_log("Error in opening file," . $filename);
		exit();
	}

	# Read the file into a variable
	$size    = filesize($filename);
	$content = fread($file, $size);

	# encode the data for safe transit
	# and insert \r\n after every 76 chars.
	$encoded_content = chunk_split( base64_encode($content));

	# Get a random 32 bit number using time() as seed.
	$num = md5( time() );

	# Define the main headers.
	$header = "From:reporting_inpost.co.uk@robo.integer.pl\r\n";
	$header .= "MIME-Version: 1.0\r\n";
	$header .= "Content-Type: multipart/mixed; ";
	$header .= "boundary=$num\r\n";
	$header .= "--$num\r\n";

	# Define the message section
	$header .= "Content-Type: text/plain\r\n";
	$header .= "Content-Transfer-Encoding:8bit\r\n\n";
	$header .= "$message\r\n";
	$header .= "--$num\r\n";

	# Define the attachment section
	$header .= "Content-Type:  multipart/mixed; ";
	$header .= "name=\"$short_filename\"\r\n";
	$header .= "Content-Transfer-Encoding:base64\r\n";
	$header .= "Content-Disposition:attachment; ";
	$header .= "filename=\"$short_filename\"\r\n\n";
	$header .= "$encoded_content\r\n";
	$header .= "--$num--";

	$retval = mail($email_address,
		$subject, "",
		$header);

	if($retval == true)
	{
		error_log("Sent email successfully.");
	}
	else
	{
		error_log("Failed to email successfully.");
	}

	fclose($file);
}

