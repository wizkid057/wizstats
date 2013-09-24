<?php

require_once 'includes.php';
require_once 'hashrate.php';

if (isset($_GET["format"])) {
	$format = strtolower($_GET["format"]);
	#if (($format != "json") && ($format != "text") && ($format != "html")) {
	if (($format != "json") && ($format != "text")) {
		$format = "json";
		header("Content-type: application/json");
		ws_api_error("Invalid format");
	}
} else {
	$format = "json";
}

if ($format == "json") { header("Content-type: application/json"); }
if ($format == "text") { header("Content-type: text/plain"); }
#if ($format == "html") { header("Content-type: text/html"); }


if (isset($_GET["cmd"])) {
	$cmd = strtolower($_GET["cmd"]);
} else {
	ws_api_error("No command");
}

$data = array("cmd" => $cmd, "stime" => time());


if ($cmd == "getacceptedcount") {
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if ((isset($_GET["user_id"])) && (is_numeric($_GET["user_id"])) ) {
		$user_id =  pg_escape_string($_GET["user_id"]);
	} else {
		ws_api_error("$cmd: No valid user id specified");
	}
	if (isset($_GET["startdate"])) {
		$startdate = pg_escape_string($link, $_GET["startdate"]);
		if (($_GET["startdate"] != $startdate) || (strtotime($startdate) < 1368483761)) { ws_api_error("$cmd: Invalid start date"); }
	} else {
		$startdate = "NOW()-'3 hours'::interval";
	}
	if (isset($_GET["enddate"])) {
		$enddate = pg_escape_string($link, $_GET["enddate"]);
		if (($_GET["enddate"] != $enddate) || (strtotime($enddate) < 1368483761)) { ws_api_error("$cmd: Invalid end date"); }
	}

	$sql = "select sum(accepted_shares) as count,sum(rejected_shares) as rcount,max(time) as ltime,min(time) as ftime from $psqlschema.stats_shareagg where server=$serverid and user_id=$user_id and time >= '$startdate' ".(isset($enddate)?" and time <= '$enddate'":"").";";
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
	if ((isset($_GET["address"])) && (ctype_alnum($_GET["address"])) ) {
		$givenuser =  $_GET["address"]);
	} else {
		ws_api_error("$cmd: No valid address specified");
	}

	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if (pg_connection_status($link) != PGSQL_CONNECTION_OK) {
		ws_api_error("Unable to establish a connection to the stats database.  Please try again later. If this issue persists, please report it to the pool operator.");
	}
	$user_id = get_user_id_from_address($link, $givenuser);
	if (!$user_id) {
		ws_api_error("Address $givenuser not found in database.  Please try again later. If this issue persists, please report it to the pool operator.");
	}

	// Can probably use some cache here
	$cache_key = hash("sha256", "api.php hashrate json for $givenuser");
	$json = get_stats_cache($link, 200, $cache_key);
	if ($json == "") {
		$hashrate_info = get_hashrate_stats($link, $givenuser, $user_id);
		$output = array();
		$output["values"] = array();
		$output["intervals"] = $hashrate_info["intervals"];
		foreach ($hashrate_info["intervals"] as $interval) {
			$output["values"][$interval] = $hashrate_info[$interval];
		}
		$json = json_encode($output);
		set_stats_cache($link, 200, $cache_key, $json, 30);
	}
	$data["output"] = $json;
	echo ws_api_encode($data);
}

ws_api_error("Command not found");

function ws_api_encode($data) {
	if ($GLOBALS["format"] == "json") {
		return json_encode($data);
	}
	if ($GLOBALS["format"] == "text") {
		print_r($data);
	}

}

function ws_api_error($msg) {

	if (strlen($msg) == 0) { $msg = "Unknown"; }
	$data = array("error" => $msg, "stime" => time());
	echo ws_api_encode($data);
	exit;
}
