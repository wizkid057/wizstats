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
	if (($format != "json") && ($format != "text")) {
		$format = "json";
		header("Content-type: application/json");
		ws_api_error("Invalid format");
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
	if ((isset($_GET["full"])) && ($_GET["full"] == 1)) {
		$full = 1;
	} else {
		$full = 0;
	}

	if (isset($_GET["username"])) {
		$givenuser = $_GET["username"];
		$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

		$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
		$user_id = get_user_id_from_address($link, $givenuser);
		if (!$user_id) {
			ws_api_error("$cmd: Username $givenuser not found in database.");
		}
	} else {
		$givenuser = "entirepool";
	}

	if($cppsrbjsondec = apc_fetch('cppsrb_json')) {
	} else {
	        $cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
	        $cppsrbjsondec = json_decode($cppsrbjson, true);
	        apc_store('cppsrb_json', $cppsrbjsondec, 60);
	}

	if ($givenuser != "entirepool") {
		$mycppsrb = $cppsrbjsondec[$givenuser];
	} else {
		$mycppsrb = $cppsrbjsondec[""];
	}

	$my_shares = $mycppsrb["shares"];

	$output = array();
	$output["username"] = $givenuser;
	$output["av256"] = array("numeric" => sprintf("%.0F",($my_shares[256] * 4294967296)/256), "pretty" => prettyHashrate(($my_shares[256] * 4294967296)/256), "share_count" => sprintf("%u",$my_shares[256]));
	$output["av128"] = array("numeric" => sprintf("%.0F",($my_shares[128] * 4294967296)/128), "pretty" => prettyHashrate(($my_shares[128] * 4294967296)/128), "share_count" => sprintf("%u",$my_shares[128]));
	$output["av64"] = array("numeric" => sprintf("%.0F",($my_shares[64] * 4294967296)/64), "pretty" => prettyHashrate(($my_shares[64] * 4294967296)/64), "share_count" => sprintf("%u",$my_shares[64]));
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

ws_api_error("Command not found");


function ws_api_encode($data) {
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

}


function ws_api_error($msg) {

	if (strlen($msg) == 0) { $msg = "Unknown"; }
	$data = array("error" => $msg, "stime" => time());
	echo ws_api_encode($data);
	exit;
}
