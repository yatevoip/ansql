<?php
/**
 * lib-test.php
 *
 * Copyright (C) 2019 Null Team
 *
 * This software is distributed under multiple licenses;
 * see the COPYING file in the main directory for licensing
 * information for this specific distribution.
 *
 * This use of this software may be subject to additional restrictions.
 * See the LEGAL file in the main directory for details.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */ 
function test_curl_request($url, $content=null, $ctype=null, $method='post', $print_url=true)
{
	// for tests purpose use the next line and in terminal 'ngrep -d lo -W byline host 127.0.0.1 and port 80'
	// $_SERVER['HTTP_HOST'] = '127.0.0.1';
	$_SERVER['REQUEST_SCHEME'] = "http";
	$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] .
		preg_replace('/\/test\/.*/', '/', $_SERVER["REQUEST_URI"]) . $url;
	$curl = curl_init($url);
	if ($curl === false) {
		exit;
	}
	if ($print_url)
		print $url;

	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); # Equivalent to -k or --insecure 
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); # maybe needs to be set to 0 or 1 
	//curl_setopt($curl,CURLOPT_POSTFIELDS,gzdeflate(json_encode($out)));
	// if not set the method is GET
	if ($method == 'put')
		 curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
	elseif ($method == 'delete')
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
	elseif ($method == 'post')
		curl_setopt($curl, CURLOPT_POST, true);

	if (!empty($content)) {
		if (is_array($content))
			$content = json_encode($content);
		curl_setopt($curl, CURLOPT_POSTFIELDS,$content);
		
	}
	elseif ($method == 'post')
		curl_setopt($curl, CURLOPT_POSTFIELDS, "");

	$hdr = array(
		"X-Authentication: mmi_secret",
		"Accept: application/json,text/x-json,application/x-httpd-php",
		"Accept-Encoding: gzip, deflate",
		// if we decide to gzip data from site to api as well
		// "Content-Encoding: gzip"
	);
	if (!empty($ctype))
		$hdr[] = "Content-type: " . $ctype;
	curl_setopt($curl,CURLOPT_HTTPHEADER, $hdr);
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
	curl_setopt($curl,CURLOPT_TIMEOUT,40);
	curl_setopt($curl,CURLOPT_HEADER, true);

	$ret = curl_exec($curl);
	if ($ret === false) {
		print " FAIL";
	}
	else {
		$info = curl_getinfo($curl);

/*		$raw_headers = explode("\n", substr($ret, 0, $info['header_size']) );
		$gzip = false;
		foreach ($raw_headers as $header) {
			if (preg_match('/^(.*?)\\:\\s+(.*?)$/m', $header, $header_parts) ){
				$headers[$header_parts[1]] = $header_parts[2];
				if ($header_parts[1] == "Content-Encoding" && substr(trim($header_parts[2]),0,4)=="gzip")
					$gzip = true;
			}
		}

		if ($gzip) {
			$zipped_ret = substr($ret,$info['header_size']);
			$ret = gzinflate(substr($zipped_ret,10));
		} else*/
		$message = explode(' ', trim(strtok($ret, "\n")),3);
		$message= $message[2];
		$ret = substr($ret,$info['header_size']);

		$code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
		$type = curl_getinfo($curl,CURLINFO_CONTENT_TYPE);
		
		if ($print_url)
			print " OK, code=$code, type='$type'<br><pre>$ret</pre>";
		if ($type == "application/json") {
			$inp = json_decode($ret,true);
			print("<pre>");var_dump($inp);print("</pre>");
		} else {
			print $message;
		}
	}
	curl_close($curl);

}
