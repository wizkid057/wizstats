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

### This script is for removing duplicate code
### Include when this data needs to be created

	# in case we're in a function....
	include("config.php");

	if (isset($argv[2])) { $nocache = 1; } else { $nocache = 0; }

	if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

	$livedata = get_stats_cache($link, 5, "livedata.json"); // 30secends will expires, see below.

	// if $livedata not empty and $nocache is 0. means use cache.
	if (($livedata != "") && (!$nocache)) {
		# we can parse it faster maybe?!

		$instantjsondec = json_decode($livedata,true);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
            break;
            case JSON_ERROR_DEPTH:
                echo ' - Maximum stack depth exceeded';
            break;
            case JSON_ERROR_STATE_MISMATCH:
                echo ' - Underflow or the modes mismatch';
            break;
            case JSON_ERROR_CTRL_CHAR:
                echo ' - Unexpected control character found';
            break;
            case JSON_ERROR_SYNTAX:
                echo ' - Syntax error, malformed JSON';
            break;
            case JSON_ERROR_UTF8:
                echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
            default:
                echo ' - Unknown error';
            break;
        }
		$phash = $instantjsondec["hashratepretty"];
		$roundduration = $instantjsondec["roundduration"];
		$sharesperunit = $instantjsondec["sharesperunit"];
		$netdiff = $instantjsondec["network_difficulty"];
		$roundshares = $instantjsondec["roundsharecount"];
		$blockheight = $instantjsondec["lastblockheight"];
		$latestconfirms = $instantjsondec["lastconfirms"];
		$datanew = 0;
	} else {
		$sql = "select pg_try_advisory_lock(1000002) as l"; // return true or false, "t", or "f"
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$lock = $row["l"];

		if ($lock == "f") {
			for($t=0;$t<15;$t++) {
				sleep(2);
				$livedata = get_stats_cache($link, 5, "livedata.json");
				if ($livedata != "") {
					$instantjsondec = json_decode($livedata,true);
					$phash = $instantjsondec["hashratepretty"];
					$roundduration = $instantjsondec["roundduration"];
					$sharesperunit = $instantjsondec["sharesperunit"];
					$netdiff = $instantjsondec["network_difficulty"];
					$roundshares = $instantjsondec["roundsharecount"];
					$blockheight = $instantjsondec["lastblockheight"];
					$latestconfirms = $instantjsondec["lastconfirms"];
					$datanew = 0;
					$t = 100;
				}
			}
			if ($t < 100) {
				# Error!
				$tline = "{\"error\":\"Could not retrieve live stats\"}\n";
			}
		} else {


			# get latest network difficulty from latest accepted share's "bits" field
			$sql = "select id,(pow(10,((29-$psqlschema.hex_to_int(substr(encode(solution,'hex'),145,2)::varchar))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  $psqlschema.hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where server=$serverid and our_result=true order by id desc limit 1;";
			//echo $sql;
			$result = pg_exec($link, $sql);
			if(pg_num_rows($result) == 0){
				# Error! there is nodata in shares table
				$tline = "{\"error\":\"Could not retrieve live stats, No data in shares table\"}\n";
			} else {


			$row = pg_fetch_array($result, 0);

			$netdiff = $row["network_difficulty"];
			# Get the share id of the last valid block we've found
			$sql = "select * from (select orig_id,time from $psqlschema.stats_blocks where server=$serverid and confirmations > 0 order by time desc limit 1) as a, (select time+'675 seconds'::interval as satime from $psqlschema.stats_shareagg where server=$serverid order by time desc limit 1) as b;";
			$result = pg_exec($link, $sql);
			$row = pg_fetch_array($result, 0);
			$tempid = $row["orig_id"];
			$temptime = $row["time"];
			$temptime2 = $row["satime"];

			# check cache for latest speed boost data
			if ((!($livedataspeedup = apc_fetch("livedata.json - share count from $tempid"))) || ($nocache)){
				# no boost for this block yet, lets make it!
				# this is kind of a kludge, bit should be close enough for the instastats...
				$sql = "select (select sum(pow(2,targetmask-32)) from shares where server=$serverid and our_result=true and time > '$temptime' and time < to_timestamp(((date_part('epoch', '$temptime'::timestamp without time zone)::integer / 675) * 675)+675))+(select coalesce(sum(accepted_shares),0) from $psqlschema.stats_shareagg where time >= to_timestamp(((date_part('epoch', '$temptime'::timestamp without time zone)::integer / 675) * 675)+675) and server=$serverid)+(select coalesce(sum(pow(2,targetmask-32)),0) from shares where server=$serverid and our_result=true and time > '$temptime2' and time > to_timestamp(((date_part('epoch', '$temptime'::timestamp without time zone)::integer / 675) * 675)+675)) as instcount, (select id from shares where server=$serverid order by id desc limit 1) as latest_id;";
				if ($nocache) { print $sql; }
				$sqlescape = pg_escape_string($link, $sql);
				$sqlcheck = "select count(*) as check from pg_stat_activity where query='$sqlescape'";
				$result = pg_exec($link, $sqlcheck); $row = pg_fetch_array($result, 0);
				$runningqueries = $row["check"];
				$fetch = 1;
				if ($runningqueries > 0) {
					# someone else is already running this possibly intense query..
					$done = 10;
					while($done > 0) {
						$done--;
						sleep(1);
						$livedataspeedup = apc_fetch("livedata.json - share count from $tempid");
						if ($livedataspeedup != "") {
								# woot, fruits of the other query's labor!
								list($roundshares, $maxid) = explode(":", $livedataspeedup);
								$done = 0; $fetch = 0;
						}
					}
				}
				if ($fetch == 1) {
					$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
					$roundshares = $row["instcount"];
					$maxid = $row["latest_id"];
					if (($roundshares > 0) && ($maxid > $tempid)) {
						apc_store("livedata.json - share count from $tempid", "$roundshares:$maxid", 86400*7);
					}
				}
			} else {
				list($roundshares, $maxid) = explode(":", $livedataspeedup);
				if ($maxid < $tempid) {
					# should never happen, but just in case
					print "{\"error\":\"$maxid < $tempid ... $livedataspeedup\"}\n";
					exit();
				}
				# this should still be pretty fast after caching
				$sql = "select sum(pow(2,targetmask-32)) as instcount, max(id) as latest_id from shares where server=$serverid and our_result=true and id > $maxid";
				$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
				$roundshares += $row["instcount"];
				$maxid = $row["latest_id"];
				if (($roundshares > 0) && ($maxid > $tempid)) {
					apc_store("livedata.json - share count from $tempid", "$roundshares:$maxid", 86400*7);
				}
			}

			# get hashrate
			if($cppsrbjsondec = apc_fetch('cppsrb_json_inst')) {
				$hashrate256 = $cppsrbjsondec[""]["shares"][256] * 16777216;
			} else {
				if (filemtime("$pooldatadir/$serverid/cppsrb.json") > (time()-600)) {
					$cppsrbjson = file_get_contents("$pooldatadir/$serverid/cppsrb.json");
					$cppsrbjsondec = json_decode($cppsrbjson, true);
					apc_store('cppsrb_json_inst', $cppsrbjsondec, 60);
					$hashrate256 = $cppsrbjsondec[""]["shares"][256] * 16777216;

					# note that CPPSRB is ok
					apc_store('cppsrb_ok', 1, 60);
				} else {
					$sql2 = "select (date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer as sql2res";
					$result = pg_exec($link, $sql2); $row = pg_fetch_array($result, 0);
					$sql2res = $row["sql2res"];

					$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash,sum(accepted_shares) as share_total from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp($sql2res)-'1350 seconds'::interval";
					$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
					$hashrate256 = $row["avghash"];


					# note that cppsrb is not ok
					apc_store('cppsrb_ok', -1, 90);
				}

			}

			# get latest block height
			$sql = "select date_part('epoch',NOW() - time) as roundduration,height,confirmations from $psqlschema.stats_blocks where server=$serverid and confirmations > 0 and height > 0 order by height desc limit 1;";
			$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
			$blockheight = $row["height"];
			$roundduration = $row["roundduration"];
			$latestconfirms = $row["confirmations"];


			$sharesperunit = ($hashrate256/4294967296)/20;

			$phash = prettyHashrate($hashrate256);
			$datanew = 1;
			$sql = "select pg_advisory_unlock(1000002) as l";
			$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		} // nodata in shares
		}

	}
	//if (!($roundshares > 0)) { $roundshares = 0; }
	// pay attention to below lines!!!!! debug use!
	if (!($roundshares > 0)) { $roundshares = 1; }

	if(!$sharesperunit)$sharesperunit=1;
	if(!$blockheight)$blockheight=1;
	if(!$latestconfirms)$latestconfirms=0;
	if(!$latestconfirms)$latestconfirms=0;
	if(!$roundduration)$roundduration=1;
	if(!$netdiff)$netdiff=1;



	$tline = "{\"sharesperunit\":$sharesperunit,\"roundsharecount\":$roundshares,\"lastblockheight\":$blockheight,\"lastconfirms\":$latestconfirms,\"roundduration\":$roundduration,\"hashratepretty\":\"$phash\",\"network_difficulty\":$netdiff}";

	if ($datanew) {
		# cache this for 30 seconds, should be good enough
		set_stats_cache($link, 5, "livedata.json", $tline, 30);
	}
