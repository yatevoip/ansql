<?php


function translate_error_to_code($res)
{
	if (!is_array($res) || count($res)<2 || !isset($res[0]))
		return array(false, "500", "Internal Server Error: wrong result format from called function.");
	if ($res[0])
		return array(true, "0", $res[1]); // success
	
	if (isset($res[1])) {
		if (substr($res[1], -12)==" is required" || substr($res[1], -9)==" not set.")
			return array(false, "402", "Missing or empty parameters ". $res[1]);
		else
			return array(false, "500", "Internal Server Error: database generated: ".$res[1] );
	}
}

function build_success($data, $params)
{
	return array(
		"code"		=> 200,
		"message"	=> "OK",
		"params"	=> $params,
		"data"		=> array_merge(array("code" => 0), $data)
	);
}

function build_error($code, $message, $params = array())
{
	return array(
		"code"		=> $code, 
		"message"	=> $message,
		"params"	=> $params,
		"data" 		=> array(
			"code"      => $code,
			"message"   => $message
		)
	);
}

function log_request($inp, $out = null)
{
	global $logs_file;
	global $logs_dir;

	$addr = "unknown";
	if (isset($_SERVER['REMOTE_ADDR']))
		$addr = $_SERVER['REMOTE_ADDR'];

	if (!$logs_file) {
		if (false !== $logs_file)
			print "\n// Not writeable $logs_dir";
		return;
	}
	$day = date("Y-m-d");
	$file = str_replace(".txt","$day.txt",$logs_file);
	$fh = fopen($file, "a");
	if ($fh) {
		if ($out === null)
			$out = "";
		else 
			$out = "Response: ".json_encode($out)."\n";

		fwrite($fh, "------ " . date("Y-m-d H:i:s") . ", ip=$addr\nURI: ".$_SERVER['REQUEST_URI']."\nRequest: ".json_encode($inp)."\n$out\n");
		fclose($fh);
	}
	else
		print "\n// Can't write to $file";
}
