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

	if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

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

		$sql = "select pg_try_advisory_lock(1000002) as l";
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
			$sql = "select id,(pow(10,((29-hex_to_int(substr(encode(solution,'hex'),145,2)))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where server=$serverid and time < (select time from $psqlschema.stats_shareagg where server=$serverid order by id desc limit 1) and our_result=true order by id desc limit 1;";
			$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
			$netdiff = $row["network_difficulty"];

			# Get the share id of the last valid block we've found
			$sql = "select orig_id from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1;";
			$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
			$tempid = $row["orig_id"];

			# check cache for latest speed boost data
			$livedataspeedup = get_stats_cache($link, 105, "livedata.json - share count from $tempid");
			if ($livedataspeedup == "") {
				# no boost for this block yet, lets make it!
				$sql = "select sum(our_result::integer * pow(2,targetmask-32)) as instcount, max(id) as latest_id from shares where server=$serverid and our_result=true and id > $tempid";
				$sqlcheck = "select count(*) as check from pg_stat_activity where current_query='$sql'";
				$result = pg_exec($link, $sqlcheck); $row = pg_fetch_array($result, 0);
				$runningqueries = $row["check"];
				$fetch = 1;
				if ($runningqueries > 0) {
					# someone else is already running this possibly intense query..
					$done = 10;
					while($done > 0) {
						$done--;
						sleep(1);
						$livedataspeedup = get_stats_cache($link, 105, "livedata.json - share count from $tempid");
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
						set_stats_cache($link, 105, "livedata.json - share count from $tempid", "$roundshares:$maxid", 86400*7);
					}
				}
			} else {
				list($roundshares, $maxid) = explode(":", $livedataspeedup);
				if ($maxid < $tempid) {
					# should never happen, but just in case
					print "{\"error\":\"$maxid < $tempid ... $livedataspeedup\"}\n";
					exit();
				}
				$sql = "select sum(our_result::integer * pow(2,targetmask-32)) as instcount, max(id) as latest_id from shares where server=$serverid and our_result=true and id > $maxid";
				$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
				$roundshares += $row["instcount"];
				$maxid = $row["latest_id"];
				if (($roundshares > 0) && ($maxid > $tempid)) {
					update_stats_cache($link, 105, "livedata.json - share count from $tempid", "$roundshares:$maxid", 86400*7);
				}
			}

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
			$sql = "select pg_advisory_unlock(1000002) as l";
			$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		}

	}
	if (!($roundshares > 0)) { $roundshares = 0; }

	$tline = "{\"sharesperunit\":$sharesperunit,\"roundsharecount\":$roundshares,\"lastblockheight\":$blockheight,\"lastconfirms\":$latestconfirms,\"roundduration\":$roundduration,\"hashratepretty\":\"$phash\",\"network_difficulty\":$netdiff}";

	if ($datanew) {
		# cache this for 30 seconds, should be good enough
		set_stats_cache($link, 5, "livedata.json", $tline, 30);
	}
