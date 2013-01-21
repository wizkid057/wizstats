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

### This script is for removing duplicate code
### Include when this data needs to be created

	# in case we're in a function....
	include("config.php");

	$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

	$livedata = get_stats_cache($link, 5, "livedata.json");
	if ($livedata != "") {
		# we can parse it faster maybe?!

		$instantjsondec = json_decode($livedata,true);

		$phash = $instantjsondec["hashratepretty"];
		$roundduration = $instantjsondec["roundduration"];
		$sharesperunit = $instantjsondec["sharesperunit"];
		$netdiff = $instantjsondec["network_difficulty"];
		$roundshares = $instantjsondec["roundsharecount"];
		$blockheight = $instantjsondec["lastblockheight"];
		$latestconfirms = $instantjsondec["lastconfirms"];
		$datanew = 0;
	} else {

		# get round share count...
		$sql = "select ((select id from shares where server=$serverid and time < (select time from $psqlschema.stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1)-(select orig_id-coalesce(rightrejects,0) from $psqlschema.stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1)-(select coalesce(sum(rejected_shares),0) from $psqlschema.stats_shareagg where time >= (select to_timestamp((date_part('epoch', time)::integer / 675::integer)::integer * 675::integer) from $psqlschema.stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1))) as currentround;";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$roundshares = $row["currentround"];

		$sql = "select id,(pow(10,((29-hex_to_int(substr(encode(solution,'hex'),145,2)))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where server=$serverid and time < (select time from $psqlschema.stats_shareagg where server=$serverid order by id desc limit 1) and our_result=true order by id desc limit 1;";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$tempid = $row["id"];
		$netdiff = $row["network_difficulty"];

		$sql = "select count(*) as instcount from shares where server=$serverid and our_result=true and id > $tempid";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$roundshares += $row["instcount"];

		# get hashrate
		$cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
		$cppsrbjsondec = json_decode($cppsrbjson,true);
		$hashrate256 = $cppsrbjsondec[""]["shares"][256] * 16777216;


		# get latest block height
		$sql = "select date_part('epoch',NOW() - time) as roundduration,height,confirmations from $psqlschema.stats_blocks where server=$serverid and confirmations > 0 and height > 0 order by id desc limit 1;";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$blockheight = $row["height"];
		$roundduration = $row["roundduration"];
		$latestconfirms = $row["confirmations"];


		$sharesperunit = ($hashrate256/4294967296)/20;


		$phash = prettyHashrate($hashrate256);
		$datanew = 1;

	}
	if (!($roundshares > 0)) { $roundshares = 0; }

	$tline = "{\"sharesperunit\":$sharesperunit,\"roundsharecount\":$roundshares,\"lastblockheight\":$blockheight,\"lastconfirms\":$latestconfirms,\"roundduration\":$roundduration,\"hashratepretty\":\"$phash\",\"network_difficulty\":$netdiff}";

	if ($datanew) {
		# cache this for 30 seconds, should be good enough
		set_stats_cache($link, 5, "livedata.json", $tline, 30);
	}
