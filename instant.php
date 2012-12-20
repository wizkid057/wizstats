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

require_once 'config.php';
require_once 'blocks_functions.php';


if (!isset($_SERVER['PATH_INFO'])) { exit(); }

if ($_SERVER['PATH_INFO'] == "/livedata.json") {

	header("Content-type: application/json");

	$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

	# get round share count...
	$sql = "select ((select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1)-(select orig_id-coalesce(rightrejects,0) from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1)-(select coalesce(sum(rejected_shares),0) from stats_shareagg where time >= (select to_timestamp((date_part('epoch', time)::integer / 675::integer)::integer * 675::integer) from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1))) as currentround;";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$roundshares = $row["currentround"];

	$sql = "select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1;";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$tempid = $row["id"];
	$sql = "select count(*) as instcount from shares where server=$serverid and our_result=true and id > $tempid";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$roundshares += $row["instcount"];

	# get hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$hashrate1250 = $row["avghash"];

	# get latest block height
	$sql = "select date_part('epoch',NOW() - time) as roundduration,height,confirmations from stats_blocks where server=$serverid and confirmations > 0 and height > 0 order by id desc limit 1;";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$blockheight = $row["height"];
	$roundduration = $row["roundduration"];
	$latestconfirms = $row["confirmations"];


	$sharesperunit = ($hashrate1250/4294967296)/20;

	if (!($roundshares > 0)) { $roundshares = 0; }

	print "{\"sharesperunit\":$sharesperunit,\"roundsharecount\":$roundshares,\"lastblockheight\":$blockheight,\"lastconfirms\":$latestconfirms,\"roundduration\":$roundduration}";
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

	$result = pg_exec($link, $sql); 
	$row = pg_fetch_array($result, 0);
	$dbid = $row["blockid"];
	if (isset($_GET["cclass"])) { $cclass = $_GET["cclass"]; } else { $cclass = ""; }
	if (substr($cclass,0,3) == "odd") { $isodd = "odd"; } else { $isodd = ""; }
	$line = block_table_row($row,$isodd);


	$line = json_encode($line);
	if ($dbid) {
		print "{\"blockrow\":$line}";
	}


}


?>
