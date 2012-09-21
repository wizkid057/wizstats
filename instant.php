<?php
#    wizstats - bitcoin pool web statistics - 1StatsgBq3C8PbF1SJw487MEUHhZahyvR
#    Copyright (C) 2012  Jason Hughes <wizkid057@gmail.com>
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

include("config.php");


if (!isset($_SERVER['PATH_INFO'])) { exit(); }

if ($_SERVER['PATH_INFO'] == "/livedata.json") {

	header("Content-type: application/json");

	$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

	# get round share count...
	$sql = "select ((select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1)-(select orig_id-coalesce(rightrejects,0) from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1)-(select coalesce(sum(rejected_shares),0) from stats_shareagg where time >= (select to_timestamp((date_part('epoch', time)::integer / 675::integer)::integer * 675::integer) from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1))+(select count(*) from shares where server=$serverid and our_result=true and id > (select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1))) as currentround;";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$roundshares = $row["currentround"];

	# get hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$hashrate1250 = $row["avghash"];

	# get latest block height
	$sql = "select height from stats_blocks where server=$serverid and confirmations > 0 and height > 0 order by id desc limit 1;";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$blockheight = $row["height"];

	$sharesperunit = ($hashrate1250/4294967296)/20;

	if (!($roundshares > 0)) { $roundshares = 0; }

	print "{\"sharesperunit\":$sharesperunit,\"roundsharecount\":$roundshares,\"lastblockheight\":$blockheight}";
	exit();

}

if ($_SERVER['PATH_INFO'] == "/blockinfo.json") {

	if ( (!isset($_GET["height"])) && (!isset($_GET["dbid"]))) { exit(); }

	$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if (isset($_GET["height"])) { 
		$cleanheight = pg_escape_string($link, $_GET["height"]); 
		$sql = "select *,stats_blocks.id as blockid,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from stats_blocks left join users on user_id=users.id where height=$cleanheight;";
	}
	if (isset($_GET["dbid"])) { 
		$cleandbid = pg_escape_string($link, $_GET["dbid"]); 
		$sql = "select *,stats_blocks.id as blockid,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from stats_blocks left join users on user_id=users.id where stats_blocks.id=$cleandbid;";

	}

	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);

	$line = "";

	if (isset($row["acceptedshares"])) { $luck = 100 * ($row["network_difficulty"] / $row["acceptedshares"]); } else { $luck = 0; }
	if ($luck > 9999) { $luck = ">9999%"; } else { $luck = round($luck,2)."%"; }


	$roundstart = substr($row["roundstart"],0,19);
	if ($row["confirmations"] >= 120) { $confs = "Confirmed"; }
	else if ($row["confirmations"] == 0) { $confs = "Stale"; $oc++; $luck = "n/a"; $roundstart = "<SMALL>(".substr($row["time"],0,19); $roundstart .= ")</SMALL>"; }
	else { $confs = $row["confirmations"]." of 120"; }

	if (isset($_GET["cclass"])) { $cclass = $_GET["cclass"]; } else { $cclass = ""; }

	if (substr($cclass,0,3) == "odd") { $isodd = "odd"; } else { $isodd = ""; }
	$dbid = $row["blockid"];
	if ($row["confirmations"] == 0) { $line .= "<TR id=\"blockrow$dbid\" BGCOLOR=\"#FFDFDF\" class=\"$isodd"."blockorphan\">"; } 
	else if ($row["confirmations"] >= 120) { $line .= "<TR id=\"blockrow$dbid\" BGCOLOR=\"#DFFFDF\" class=\"$isodd"."blockconfirmed\">"; }
	else { $line .= "<TR id=\"blockrow$dbid\" class=\"$isodd\">"; }

	$line .= "<TD>".prettyDuration($row["age"],false,1)."</TD>";

	$line .= "<TD>".$roundstart."</TD>";

	if (isset($row["duration"])) {
		list($seconds, $minutes, $hours) = extractTime($row["duration"]);
		$line .= "<td style=\"width: 1.5em;  text-align: right;\">$hours</td><td style=\"width: 1.5em;  text-align: right;\">$minutes</td><td style=\"width: 1.5em;  text-align: right;\">$seconds</td>";
	} else {
		$line .= "<td style=\"text-align: right;\" colspan=\"3\">n/a</td>";
	}

	$line .= "<TD style=\"text-align: right;\">".$row["acceptedshares"]."</TD>";

	if (isset($row["rejectedshares"])) {
		$rper = "<SMALL>(".round(  (($row["rejectedshares"]/($row["rejectedshares"]+$row["acceptedshares"])) *100) ,2)."%)</SMALL>";
		$line .= "<TD style=\"text-align: right;\">".$row["rejectedshares"]."</TD><TD style=\"text-align: right;\">".$rper."</TD>";
	} else {
		$line .= "<TD colspan=\"2\" style=\"text-align: right;\">n/a</TD>";
	}

	$line .= "<TD style=\"text-align: right;\">".sprintf("%.3e",round($row["network_difficulty"],4))."</TD>";
	$line .= "<TD style=\"text-align: right;\">".$luck."</TD>";
	$line .= "<TD style=\"text-align: right;\">".$confs."</TD>";
	if (isset($row['keyhash'])) {
		$fulladdress =  \Bitcoin::hash160ToAddress(bits2hex($row['keyhash']));
		$address = substr($fulladdress,0,10)."...";
	} else {
		 $address = "(Unknown user)"; 
	}
	$line .= "<TD><A HREF=\"userstats.php/".$fulladdress."\">".$address."</A></TD>";


	if ((isset($row["height"])) && ($row["height"] > 0)) {
		$ht = $row["height"];
	} else {
		$ht = "n/a";
	}
	$line .= "<TD style=\"text-align: right;\">$ht</TD>";

	$nicehash = "...".substr($row["blockhash"],40,24);
	$line .= "<TD><A HREF=\"http://blockchain.info/block/".$row["blockhash"]."\">".$nicehash."</A></TD></TR>";

	$line = json_encode($line);
	if ($dbid) {
		print "{\"blockrow\":$line}";
	}


}


?>
