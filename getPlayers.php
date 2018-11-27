<?php
	/*
	
	This gets all the usernames and userIds of all users of a specific rank from a specific group (and exports them in json format).
	
	[Get] Parameters:
		group: The group you want to index
		rank: Which rank you want to index
		getAll: Indexes all ranks of the group ID
		raw: Set this to false and it shows get time and player count
		online: Set this to true and it only gets online players
		limit: Limits number of players indexed PER RANK (won't limit everything in get all; -1 for no limit, which is default)
	Examples:
		/getPlayers.php?group=18&rank=255
		/getPlayers.php?getAll=6079&limit=100&raw=false
		[with whatever parameters you'd like]
	
	I want to give a really special thanks to Casualist for helping me fix a critical bug that occured with certain groups.
	The first request would error but ONLY FOR CERTAIN GROUPS, which is what threw me off so much. I didn't know what would be different on different group pages (I'm still not sure)
	With the help of Casualist's working bot I tracked the problem down to a SINGLE EXTRA INPUT that was being picked up by getFullPostArray.
	The reason it exists on some pages and not others I still do not know.
	
	I was also able to implement delta downloading using his example so that it wouldn't be redownloading the entire page every request, just the stuff that changed.
	You would think that'd make it faster but its such a small amount of data it doesn't actually make a difference (and you aren't loading the images, obviously).
	It might actually make it slower but I won't revert unless I'm sure.
	
	+ Changed the post array to a whitelist now instead of a blacklist, which solves A TON of problems and actually shortens the script.
	
	*/
	include_once 'Includes/getRoles.php';
	include_once 'Includes/getPostArray.php';
	libxml_use_internal_errors(true); // Hide DomDocument parse warnings
	set_time_limit(0); // May take a while, don't want it to time out!
	$raw = array_key_exists('raw',$_GET) && $_GET['raw'] == 'false' ? false : true; // (Default to true)
	$online = array_key_exists('online',$_GET) && $_GET['online'] == 'true' ? true : false; // (Default to false)
	$limit = array_key_exists('limit',$_GET) ? $_GET['limit'] : -1; // (Default to -1, no limit)
	function getPlayersOnPage($html,$array,$limit,$online) {
		$doc = new DOMDocument();
		$doc->loadHTML($html);
		$find = new DomXPath($doc);
		$nodes = $find->query("//div[contains(@class,'Avatar')]");
		foreach ($nodes as $node) {
			if ($limit != -1 && count($array) >= $limit) {
				break;
			}
			$link = $find->query('a',$node)->item(0);
			$img = $find->query('span/img',$node)->item(0);
			if (!$online || $img->getAttribute('src') == '../images/online.png') {
				preg_match('#\d+#',$link->getAttribute('href'),$matches);
				// ..User.aspx?ID=(number)
				array_push($array,array($link->getAttribute('title') => (int)$matches[0]));
			}
		}
		return $array;
	}
	function getPlayers($ranks,$raw,$group,$rank,$limit,$online) {
		$players = array();
		$role = getRoleSet($ranks,$rank);
		$start = time();
		$url = "https://www.roblox.com/Groups/group.aspx?gid=$group";
		$curl = curl_init($url);
		// Start off by just getting the page
		// We need the correct validation before sending other requests
		curl_setopt_array($curl,array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla' // For some reason I have to do this...
		));
		$response = curl_exec($curl);
		// Include __VIEWSTATE, __EVENTVALIDATION, and __VIEWSTATEGENERATOR in the next post array
		// Set the rank we want to search from while we're at it
		$nextPost = getPostArray($response,
			array(
				'ctl00$cphRoblox$rbxGroupRoleSetMembersPane$dlRolesetList' => $role,
				'ctl00$cphRoblox$rbxGroupRoleSetMembersPane$currentRoleSetID' => $role,
				'__ASYNCPOST' => 'true', // DELTA
			)
		);
		curl_setopt_array($curl,array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $nextPost
		));
		$response = curl_exec($curl);
		$doc = new DOMDocument();
		$doc->loadHTML($response);
		$find = new DomXPath($doc);
		// Do a dance to get the number of pages
		foreach($find->query("(//div[contains(@id,'ctl00_cphRoblox_rbxGroupRoleSetMembersPane_dlUsers_Footer_ctl01_Div1')]//div[contains(@class,'paging_pagenums_container')])[1]") as $node) {
			$pages = $node->textContent;
		}
		if (!isset($pages)) {
			$pages = 1;
		}
		curl_setopt_array($curl,array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $nextPost
		));
		$response = curl_exec($curl);
		for ($i = 1; $i <= $pages; $i++) {
			if ($limit != -1 && count($players) >= $limit) {
				break;
			}
			$players = getPlayersOnPage($response,$players,$limit,$online);
			// __VIEWSTATE and __EVENTVALIDATION are not updated as inputs, rather some weird format I don't recognize
			preg_match('#\|__VIEWSTATE\|(.*?)\|.*\|__EVENTVALIDATION\|(.*?)\|#',$response,$inputs);
			$nextPost = array(
				'__VIEWSTATE' => $inputs[1],
				'__EVENTVALIDATION' => $inputs[2],
				'__ASYNCPOST' => 'true',
				'ctl00$cphRoblox$rbxGroupRoleSetMembersPane$currentRoleSetID' => $role,
				'ctl00$cphRoblox$rbxGroupRoleSetMembersPane$dlUsers_Footer$ctl01$HiddenInputButton' => '', // For some reason this is required
				'ctl00$cphRoblox$rbxGroupRoleSetMembersPane$dlUsers_Footer$ctl01$PageTextBox' => $i+1 // Next page
			);
			curl_setopt_array($curl,array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $nextPost
			));
			$response = curl_exec($curl);
		}
		if (!$raw) {
			echo 'Get time: '.(time()-$start).' seconds<br>Players: '.count($players).'<br><br>';
		}
		return $players;
	}
	if (array_key_exists('group',$_GET)) {
		$group = $_GET['group'];
		list($ranks,$roles) = getRoleSets($group);
	}
	if (array_key_exists('getAll',$_GET)) {
		$group = $_GET['getAll'];
		list($ranks,$roles) = getRoleSets($_GET['getAll']);
		$all = array();
		foreach ($ranks as $rank=>$id) {
			$all = array_merge($all,getPlayers($ranks,$raw,$group,$rank,$limit,$online));
		}
		echo json_encode($all);
	} else if (array_key_exists('rank',$_GET)) {
		echo json_encode(getPlayers($ranks,$raw,$group,$_GET['rank'],$limit,$online));
	}
?>
