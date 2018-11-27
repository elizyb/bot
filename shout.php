<?php
	include_once 'Includes/getPostArray.php';
	function shout($cookie,$group,$msg) {
		$url = "https://www.roblox.com/My/Groups.aspx?gid=$group";
		$curl = curl_init($url);
		curl_setopt_array($curl,array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_COOKIEFILE => $cookie,
			CURLOPT_COOKIEJAR => $cookie,
			CURLOPT_FOLLOWLOCATION => true
		));
		$response = curl_exec($curl);
		$nextPost = getPostArray($response,
			array(
				'ctl00$cphRoblox$GroupStatusPane$StatusTextBox' => $msg,
				'ctl00$cphRoblox$GroupStatusPane$StatusSubmitButton' => 'Group Shout'	
			)
		);
		curl_close($curl);
		$curl = curl_init($url);
		curl_setopt_array($curl,array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $nextPost,
			CURLOPT_COOKIEFILE => $cookie,
			CURLOPT_COOKIEJAR => $cookie
		));
		if (curl_exec($curl)) {
			return "Shouted $msg.";
		}
		return 'Failure';
	}
?>
