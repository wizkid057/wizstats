<?php
#    wizstats - bitcoin pool web statistics - 1StatsQytc7UEZ9sHJ9BGX2csmkj8XZr2
#    Copyright (C) 2013  Jason Hughes <wizkid057@gmail.com>
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU Affero General Public License as published by
#    the Free Software Foundation, either version 3 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.



require_once 'includes.php';
require_once 'lib.userstat.php';

$allow_csv = array('getblocks' => 1);

if (isset($_GET["cmd"])) {
	$cmd = strtolower($_GET["cmd"]);
} else {
	$cmd = "";
}

if (isset($_GET["format"])) {
	$format = strtolower($_GET["format"]);
	if ($format == "jsonp") {
		if (isset($_GET['callback'])) {
			$callback = 1;
		} else {
			$callback = 0;
		}
		$format = "json";
	} else {
		$callback = 0;
	}

	if ($format == "csv") {
		if (!array_key_exists($cmd,$allow_csv)) {
			$format = "json";
			header("Content-type: application/json");
			ws_api_error("Command does not support CSV output");
		}
	} else {
		if (($format != "json") && ($format != "text")) {
			$format = "json";
			header("Content-type: application/json");
			ws_api_error("Invalid format");
		}
	}
} else {
	$format = "json";
}

if ($format == "json") {
	if ($callback) {
		header("Content-type: application/javascript");
	} else {
		header("Content-type: application/json");
	}
}
if ($format == "text") { header("Content-type: text/plain"); }
if ($format == "csv") { 
	if (isset($_GET["csvastext"])) {
		header("Content-type: text/plain"); 
	} else {
		header("Content-type: text/csv"); 
		header('Content-Disposition: attachment;filename='.$cmd.'.csv');
	}
}

if ($cmd == "") {
	ws_api_error("No command");
}

$data = array("cmd" => $cmd, "stime" => time());


# TODO - replace with more limitted caching...
$qshash = hash("sha256", $_SERVER['QUERY_STRING']);
if ($data = apc_fetch("api.php - $cmd - $qshash")) {
	# this call is cached
	echo ws_api_encode($data);
	exit;
}

if ($cmd == "getacceptedcount") {
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if ((isset($_GET["user_id"])) && (is_numeric($_GET["user_id"])) ) {
		$user_id =  pg_escape_string($_GET["user_id"]);
	} else if (isset($_GET["username"])) {
		$givenuser = $_GET["username"];
		$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));
		$user_id = get_user_id_from_address($link, $givenuser);
		if (!$user_id) {
			ws_api_error("$cmd: Username $givenuser not found in database.");
		}
	} else {
		ws_api_error("$cmd: No valid user id specified");
	}
	if (isset($_GET["startdate"])) {
		$startdate = pg_escape_string($link, $_GET["startdate"]);
		if (($_GET["startdate"] != $startdate) || (strtotime($startdate) < 1368483761)) { ws_api_error("$cmd: Invalid start date"); }
		$startdate = "'$startdate'";
	} else {
		$startdate = "NOW()-'3 hours'::interval";
	}
	if (isset($_GET["enddate"])) {
		$enddate = pg_escape_string($link, $_GET["enddate"]);
		if (($_GET["enddate"] != $enddate) || (strtotime($enddate) < 1368483761)) { ws_api_error("$cmd: Invalid end date"); }
	}

	$worker_data = get_worker_data_from_user_id($link, $user_id);
	$wherein = get_wherein_list_from_worker_data($worker_data);

	$sql = "select sum(accepted_shares) as count,sum(rejected_shares) as rcount,max(time) as ltime,min(time) as ftime, $startdate as startdate from $psqlschema.stats_shareagg where server=$serverid and user_id in $wherein and time >= $startdate ".(isset($enddate)?" and time <= '$enddate'":"").";";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	if (!$numrows) {
		ws_api_error("$cmd: No result");
	}

	$row = pg_fetch_array($result, 0);
	$count = $row["count"];
	$rcount = $row["rcount"];
	$ftime = $row["ftime"];
	$ltime = $row["ltime"];
	$startdate = $row["startdate"];
	$data["output"] = array("user_id" => $user_id, "startdate" => $startdate, "enddate" => $enddate, "accepted" => $count, "rejected" => $rcount, "first_row_date" => $ftime, "last_row_date" => $ltime);
	echo ws_api_encode($data);
	exit;
}


if ($cmd == "getuseroptions") {
	# dump full My Eligius options
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

	if ((isset($_GET["startdate"])) && (strlen($_GET["startdate"]) > 0)) {
		$startdate = pg_escape_string($link, $_GET["startdate"]);
		if (($_GET["startdate"] != $startdate) || (strtotime($startdate) < 1368483761)) { ws_api_error("$cmd: Invalid start date"); }
	} else {
		$startdate = "2013-01-01 00:00:00";
	}


	$sql = "select (r).*,username from (select (select t from $psqlschema.stats_mystats t where t.user_id=u.id and time >= '$startdate' order by time desc limit 1) as r from users u offset 0) s left join users on (r).user_id=users.id where (r).user_id is not null and (r).server=$serverid order by id desc;";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	$output = array("count" => $numrows);
	if ($numrows) {
		for($i=0;$i<$numrows;$i++) {
			$row = pg_fetch_array($result, $i);
			# strip worker names...
			$username = substr($row["username"],0,strpos($row["username"],"_")?strpos($row["username"],"_"):128);
			$options = $row["signed_options"];
			$sig = $row["signature"];
			$stime = $row["time"];
			$sid = $row["id"];
			$suid = $row["user_id"];
			$msghead = "My ".$poolname." - ";
			$msgvars = substr($options,strlen($msghead)+26,10000);
			$msgvars = str_replace(" ","&",$msgvars);
			parse_str($msgvars, $msgvars_array);
			$line = array("username" => $username, "user_id" => $suid, "dbid" => $sid, "dbtime" => $stime, "options" => $options, "sig" => $sig, "clean_options" => $msgvars_array);
			array_push($output,$line);
		}
	}
	$data["output"] = $output;
	echo ws_api_encode($data);

	exit;

}

if ($cmd == "gethashrate") {
	$output = array();

	if (isset($_GET["username"])) {
		$givenuser = $_GET["username"];
		$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

		$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
		$user_id = get_user_id_from_address($link, $givenuser);
		if (!$user_id) {
			ws_api_error("$cmd: Username $givenuser not found in database.");
		}
		require_once 'hashrate.php';
		$hashrate_info = get_hashrate_stats($link, $givenuser, $user_id);
	} else {
		# no pool-wide longer term averages for now...
		$givenuser = "entirepool";
		if($cppsrbjsondec = apc_fetch('cppsrb_json')) {
		} else {
		        $cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
		        $cppsrbjsondec = json_decode($cppsrbjson, true);
		        apc_store('cppsrb_json', $cppsrbjsondec, 60);
		}
		$mycppsrb = $cppsrbjsondec[""];
		$my_shares = $mycppsrb["shares"];
		$output["username"] = $givenuser;
		$output["av256"] = array("numeric" => sprintf("%.0F",($my_shares[256] * 4294967296)/256), "pretty" => prettyHashrate(($my_shares[256] * 4294967296)/256), "share_count" => sprintf("%u",$my_shares[256]), "name" => "256 seconds");
		$output["av128"] = array("numeric" => sprintf("%.0F",($my_shares[128] * 4294967296)/128), "pretty" => prettyHashrate(($my_shares[128] * 4294967296)/128), "share_count" => sprintf("%u",$my_shares[128]), "name" => "128 seconds");
		$output["av64"] = array("numeric" => sprintf("%.0F",($my_shares[64] * 4294967296)/64), "pretty" => prettyHashrate(($my_shares[64] * 4294967296)/64), "share_count" => sprintf("%u",$my_shares[64]), "name" => "64 seconds");
		$data["output"] = $output;
		echo ws_api_encode($data);
		exit;
	}


	$output["username"] = $givenuser;
	foreach ($hashrate_info["intervals"] as $interval)
	{
		$hashrate_info_for_interval = $hashrate_info[$interval];
		$interval_name = $hashrate_info_for_interval["interval_name"];
		$hashrate = $hashrate_info_for_interval["hashrate"];
		$shares = $hashrate_info_for_interval["shares"];
		$output["av".$interval] = array("numeric" => sprintf("%.0F",$hashrate), "pretty" => prettyHashrate($hashrate), "share_count" => sprintf("%u",$shares), "name" => $interval_name);
	}
	$data["output"] = $output;
	echo ws_api_encode($data);
	exit;
}

if ($cmd == "getuserstat") {

	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if (isset($_GET["username"])) {
		$givenuser = $_GET["username"];
		$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));
		$user_id = get_user_id_from_address($link, $givenuser);
		if (!$user_id) {
			ws_api_error("$cmd: Username $givenuser not found in database.");
		}
	} else {
		ws_api_error("$cmd: No valid user id specified");
	}

	$data["output"] = \wizstats\userstat\getBalanceForUser($link, $user_id, $givenuser);

	echo ws_api_encode($data);
	exit;
}


if ($cmd == "getblocks") {

	if ((isset($_GET["limit"])) && (is_numeric($_GET["limit"])) ) {
		$limit =  pg_escape_string($_GET["limit"]);
		if ($limit < 0) { $limit = 1; }
	} else {
		$limit = 32;
	}

	if ((isset($_GET["offset"])) && (is_numeric($_GET["offset"])) ) {
		$offset =  pg_escape_string($_GET["offset"]);
		if ($offset < 0) { $offset = 0; }
	} else {
		$offset = 0;
	}

	if ((isset($_GET["minconfs"])) && (is_numeric($_GET["minconfs"])) ) {
		$minconfs = pg_escape_string($_GET["minconfs"]);
		if ($minconfs < 0) { $minconfs = 0; }
	} else {
		$minconfs = 1;
	}

	if ((isset($_GET["minheight"])) && (is_numeric($_GET["minheight"])) ) {
		$minheight = pg_escape_string($_GET["minheight"]);
		if ($minheight < 0) { $minheight = 0; }
	} else {
		$minheight = 0;
	}

	$sortorder = "desc";
	if ((isset($_GET["sortorder"])) && (is_numeric($_GET["sortorder"])) ) {
		if ($_GET["sortorder"] == 1) {
			$sortorder = "asc";
		}
	}
	$showpretty = 1;
	if ((isset($_GET["showpretty"])) && (is_numeric($_GET["showpretty"])) ) {
		if ($_GET["showpretty"] == 0) {
			$showpretty = 0;
		}
	}

	if (isset($_GET["sortby"])) {
		$askby = strtolower($_GET["sortby"]);

		# doing this to fully limit and allow advanced sorts
		switch($askby) {
			case "accepted_shares":
				$sortby = "acceptedshares";
				$sortby_clean = "accepted_shares";
				break;
			case "network_difficulty":
				$sortby = "network_difficulty $sortorder, time";
				$sortby_clean = "network_difficulty";
				break;
			case "percent_of_network":
				$sortby = "((acceptedshares/(date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer))/network_difficulty)";
				$sortby_clean = "percent_of_network";
				break;
			case "height":
				$sortby = "height";
				$sortby_clean = "height";
				break;
			case "duration":
				$sortby = "(time-roundstart)";
				$sortby_clean = "duration";
				break;
			case "hashrate":
				$sortby = "(acceptedshares/(date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer))";
				$sortby_clean = "hashrate";
				break;
			case "luck":
				$sortby = "(network_difficulty/acceptedshares)";
				$sortby_clean = "luck";
				break;
			case "time":
			case "age":
			case "roundstart":
			case "roundend":
			default:
				$sortby = "time";
				$sortby_clean = "time";
				break;
		}
	} else {
		$sortby = "time";
		$sortby_clean = "time";
	}

	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	$sql = "select stats_blocks.id as blockid,network_difficulty,confirmations,height,orig_id,time,user_id,keyhash,solution,blockhash,roundstart,acceptedshares,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration, date_part('epoch', time) as time_unix, date_part('epoch', roundstart) as start_unix from $psqlschema.stats_blocks left join users on user_id=users.id where confirmations >= $minconfs and height >= $minheight and server=$serverid order by $sortby $sortorder limit $limit offset $offset";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);

	$output = array();
	$output["count"] = $numrows;
	$output["options"] = array();
	$output["options"]["limit"] = $limit;
	$output["options"]["offset"] = $offset;
	$output["options"]["sortby"] = $sortby_clean;
	$output["options"]["minconfs"] = $minconfs;
	$output["options"]["minheight"] = $minheight;
	$output["options"]["showpretty"] = $showpretty;
	$output["options"]["sortorder"] = $sortorder=="desc"?"0":"1";
	if ($showpretty) { $output["options"]["sortorder_pretty"] = $sortorder=="desc"?"highest_first":"lowest_first"; }

	$output["rows"] = array();

	for($ri = 0; $ri < $numrows; $ri++) {
		$row = pg_fetch_array($result, $ri);
		$output["rows"][$ri] = array();

		if (isset($row["acceptedshares"])) { $luck = 100 * ($row["network_difficulty"] / $row["acceptedshares"]); } else { $luck = 0; }
		if ($row["confirmations"] >= 120) { $row["confirmations"] = 120; }
                list($seconds, $minutes, $hours) = extractTime($row["duration"]);
                $seconds = sprintf("%02d", $seconds);
                $minutes = sprintf("%02d", $minutes);
                $hours = sprintf("%02d", $hours);
		$hashrate = ($row["acceptedshares"] * 4294967296) / $row["duration"];

		if (isset($row['keyhash'])) {
			$fulladdress =  \Bitcoin::hash160ToAddress(bits2hex($row['keyhash']));
		} else {
			$fulladdress = "UNKNOWN_USER";
		}

		$output["rows"][$ri]["luck"] = $luck;
		$output["rows"][$ri]["confirmations"] = $row["confirmations"];
		$output["rows"][$ri]["blockhash"] = $row["blockhash"];
		$output["rows"][$ri]["height"] = $row["height"];
		$output["rows"][$ri]["duration"] = $row["duration"];
		$output["rows"][$ri]["age"] = $row["age"];
		$output["rows"][$ri]["roundstart"] = $row["start_unix"];
		$output["rows"][$ri]["roundend"] = $row["time_unix"];
		$output["rows"][$ri]["hashrate"] = sprintf("%u",$hashrate);
		$output["rows"][$ri]["percent_of_network"] = 100*($hashrate / ($row["network_difficulty"]*7158278.82667));
		$output["rows"][$ri]["accepted_shares"] = sprintf("%u",$row["acceptedshares"]);
		$output["rows"][$ri]["network_difficulty"] = sprintf("%.4f",$row["network_difficulty"]);
		$output["rows"][$ri]["block_finder"] = $fulladdress;
		$output["rows"][$ri]["block_finder_user_id"] = $row["user_id"];

		if ($showpretty) {
			$output["rows"][$ri]["luck_pretty"] = number_format(round($luck,1),1)."%";
			$output["rows"][$ri]["roundstart_pretty"] = substr($row["roundstart"],0,19);
			$output["rows"][$ri]["roundend_pretty"] = substr($row["time"],0,19);
			$output["rows"][$ri]["percent_of_network_pretty"] = number_format(round($output["rows"][$ri]["percent_of_network"],1),1)."%";
			$output["rows"][$ri]["hashrate_pretty"] = prettyHashrate($hashrate);
			$output["rows"][$ri]["age_pretty"] = prettyDuration($row["age"],false,1);
			$output["rows"][$ri]["duration_pretty"] = "$hours:$minutes:$seconds";
		}

	}
	$data["output"] = $output;
	echo ws_api_encode($data);
	exit;

}



ws_api_error("Command not found");


function ws_api_encode($data) {

	# add to or replace in cache - keep time limitted for now...
	apc_store("api.php - ".$GLOBALS["cmd"]." - ".$GLOBALS["qshash"], $data, 10);

	if ($GLOBALS["format"] == "json") {
		if ($GLOBALS["callback"]) {
			$cb = preg_replace("/[^A-Za-z0-9_]/", '', $_GET['callback']); # why do people have to be so ruthless?!
			return sprintf("%s ( %s )",$cb,json_encode($data));
		} else {
			return json_encode($data);
		}
	}
	if ($GLOBALS["format"] == "text") {
		print_r($data);
	}

	if ($GLOBALS["format"] == "csv") {
		# compile header from the $data["output"]["rows"]["0"] field...
		if (!isset($data["output"]["rows"]["0"])) {
			return "Error no rows";
		}
		$fp = fopen('php://output', 'w');
		fputcsv($fp, array_keys($data["output"]["rows"]["0"]));
		foreach($data["output"]["rows"] as $row) {
			fputcsv($fp, $row);
		}
	}

}


function ws_api_error($msg) {

	if (strlen($msg) == 0) { $msg = "Unknown"; }
	$data = array("error" => $msg, "stime" => time());
	echo ws_api_encode($data);
	exit;
}
