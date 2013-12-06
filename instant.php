<?php
#    wizstats - bitcoin pool web statistics - 1StatsQytc7UEZ9sHJ9BGX2csmkj8XZr2
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

require_once 'includes.php';
require_once 'blocks_functions.php';


if (!isset($_SERVER['PATH_INFO']) && !isset($argv[1])) { exit(); }

if (($_SERVER['PATH_INFO'] == "/livedata.json") || ((isset($argv[1])) && ($argv[1] == "l"))) {

	header("Content-type: application/json");

	if (isset($argv[2])) { $nocache = 1; } else { $nocache = 0; }

	include("instant_livedata.php");

	$tline = "{\"sharesperunit\":$sharesperunit,\"roundsharecount\":$roundshares,\"lastblockheight\":$blockheight,\"lastconfirms\":$latestconfirms,\"roundduration\":$roundduration,\"hashratepretty\":\"$phash\",\"network_difficulty\":$netdiff}";
	print $tline;


	exit();

}

if ($_SERVER['PATH_INFO'] == "/blockinfo.json") {

	if ( (!isset($_GET["height"])) && (!isset($_GET["dbid"]))) { exit(); }

	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if (isset($_GET["height"])) { 
		$cleanheight = pg_escape_string($link, $_GET["height"]); 
		$sql = "select *,stats_blocks.id as blockid,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from $psqlschema.stats_blocks left join users on user_id=users.id where height=$cleanheight;";
	}
	if (isset($_GET["dbid"])) { 
		$cleandbid = pg_escape_string($link, $_GET["dbid"]); 
		$sql = "select *,stats_blocks.id as blockid,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from $psqlschema.stats_blocks left join users on user_id=users.id where stats_blocks.id=$cleandbid;";

	}

	$tline = get_stats_cache($link, 6, hash("sha256",$sql));
	if ($tline != "") {
		print $tline;
		exit();
	}

	$result = pg_exec($link, $sql); 
	$row = pg_fetch_array($result, 0);
	$dbid = $row["blockid"];
	if (isset($_GET["cclass"])) { $cclass = $_GET["cclass"]; } else { $cclass = ""; }
	if (substr($cclass,0,3) == "odd") { $isodd = "odd"; } else { $isodd = ""; }
	$line = block_table_row($row,$isodd);


	$line = json_encode($line);
	if ($dbid) {
		$tline = "{\"blockrow\":$line}";
		print $tline;

		# cache this data for 60 seconds ... should be plenty also, could probably be higher
		set_stats_cache($link, 6, hash("sha256",$sql), $tline, 60);
	}


}


?>
