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

error_reporting(E_ALL ^ E_NOTICE);
require_once 'includes.php';

$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

$sql = "select *,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from $psqlschema.stats_blocks where server=$serverid and confirmations > 0 and height > 210000 order by time asc;";
$query_hash = hash("sha256", $sql.(isset($_GET["btc"])?"BTC":"PERCENTPPS"));
$cacheddata = get_stats_cache($link, 30, $query_hash);
if ($cacheddata != "") {
        print $cacheddata;
        exit;
}

header("Content-type: text/csv");

$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);

if (isset($_GET["btc"])) {
	$tdata = "date,est_paid,est_shelved,max_reward\n";
} else {
	$tdata = "date,est_paid,est_shelved\n";
}
print $tdata;

$unpaidbal = 0;
$shelvedshares = 0;
$myhashes = 1000000000;
$mytotalshares = 0;
$maxbal = 0;

$ignorepoints = 5;

	for($ri = 0; $ri < $numrows; $ri++) {
			$row = pg_fetch_array($result, $ri);

			$subsidy = 2500000000;
			if ($row["height"] < 210000) {
				$subsidy = 5000000000;
			}

			$diff = round($row["network_difficulty"],0);
			$shares = $row["acceptedshares"];
			$duration = $row["duration"];

			$poolspeed = $hashrate = ($shares * 4294967296) / $duration;

			$myshares = round(($myhashes / 4294967296) * $duration);
			$mytotalshares += $myshares;

			$maxearn = round((($myshares * $subsidy)/$diff));
			$maxbal += $maxearn;
			if ($diff >= $shares) {
				# lucky round
				$unpaidbal += $maxearn;
				if ($shelvedshares > 0) {
					# lucky round means I get some SS back also!
					# how lucky were we?!
					$extrafunds = $subsidy - (($shares * $subsidy)/$diff);
					$myss = round((($myhashes * $extrafunds) / $poolspeed));
					if ($shelvedshares <= $myss) { $shelvedshares = 0; $unpaidbal = $maxbal; } else { $shelvedshares -= $myss; $unpaidbal += $myss; }
				}
			} else {
				# unlucky round
				$luck = $diff/$shares;
				$earned = $luck * $maxearn;
				$shelvedshares += $maxearn - $earned;
				$unpaidbal += $earned;
			}


			$thisctime = strtotime($row["time"]);
			$date = date("Y-m-d H:i:s",$thisctime-1);
			if (isset($_GET["btc"])) {
				$tline = $date.",".sprintf("%.8f",round($unpaidbal/100000000,8)).",".sprintf("%.8f",round($shelvedshares/100000000,8)).",".sprintf("%.8f",round($maxbal/100000000,8))."\n";
				$tdata .= $tline;
				print $tline;
			} else {
				if ($ri > $ignorepoints) {
					$tline = $date.",".sprintf("%.8f",round(($unpaidbal/$maxbal)*100,8))."%,".sprintf("%.8f",round(($shelvedshares/$maxbal)*100,8))."%\n";
					$tdata .= $tline;
					print $tline;
				}
			}


	}
	set_stats_cache($link, 30, $query_hash, $tdata, 3600);

exit();


?>

