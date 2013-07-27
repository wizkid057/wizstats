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


	$cmd = $_GET["cmd"];

	$res   = isset($_GET["res"])?$_GET["res"]:1;
	$start = isset($_GET["start"])?$_GET["start"]:0;
	$back  = isset($_GET["back"])?$_GET["back"]:86400;
	if (($res < 1) || ($res > 128)) { $res = 1; }
	if (($start < 0)) { $start = 0; }
	if (($back < 675)) { $back = 675; }
	$sstart = round(($back+$start)/675)*675;
	$ressec = $res * 675;
	$start = pg_escape_string($link, $start);

	if ($cmd == "balancegraph") {

		# get balance as of last block that is relevant to this query...
		$sql = "select balance from $psqlschema.stats_balances
			where stats_balances.server=$serverid 
			and stats_balances.user_id=$user_id 
			and time < 
			(select time from $psqlschema.stats_blocks where server=7 and time < (select time from $psqlschema.stats_balances 
			where stats_balances.server=$serverid 
			and stats_balances.user_id=$user_id 
			and stats_balances.time > to_timestamp((date_part('epoch', NOW()-'$sstart seconds'::interval)::integer / 675) * 675) 
			and stats_balances.time < to_timestamp((date_part('epoch', NOW()-'$start seconds'::interval)::integer / 675) * 675) 
			order by stats_balances.time asc limit 1) order by id desc limit 1) order by id desc limit 1;";

		$query_hash = hash("sha256", $sql);
		$cacheddata = get_stats_cache($link, 3, $query_hash);
		if ($cacheddata != "") {
			$startbalance = $cacheddata + 0;
		} else {

			$result = pg_exec($link, $sql);
			$numrows = pg_numrows($result);
			if ($numrows > 0) {
				$row = pg_fetch_array($result, 0);
				$startbalance = $row["balance"];
			} else {
				$startbalance = 0;
			}
			set_stats_cache($link, 3, $query_hash, $startbalance, 675);
		}

		$sql = "select stats_balances.server as server, stats_balances.id as id, stats_balances.time as time, stats_balances.user_id as user_id, stats_balances.everpaid as everpaid,stats_balances.balance as balance,stats_balances.credit as credit, 
			(select time from $psqlschema.stats_blocks where server=$serverid and stats_blocks.confirmations > 0 and stats_blocks.time <= stats_balances.time+'675 seconds'::interval order by time desc limit 1) as blocktime
			 from $psqlschema.stats_balances 
			where stats_balances.server=$serverid 
			and stats_balances.user_id=$user_id 
			and stats_balances.time > to_timestamp((date_part('epoch', NOW()-'$sstart seconds'::interval)::integer / 675) * 675) 
			and stats_balances.time < to_timestamp((date_part('epoch', NOW()-'$start seconds'::interval)::integer / 675) * 675) 
			order by stats_balances.time asc;";

		$query_hash = hash("sha256", $sql);
		$cacheddata = get_stats_cache($link, 1, $query_hash);
		if ($cacheddata != "") {
			# this output is cached!
			print $cacheddata;
			exit;
		}

		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);

		$tdata = "date,everpaid,unpaid+everpaid+est,maximum reward,unpaid+everpaid\n";
		print $tdata;

		$lastctime = 0;

		for($ri = 0; $ri < $numrows; $ri++) {

			# 2012-12-18 03:22:30
			$row = pg_fetch_array($result, $ri);

			if (!isset($lastblocktime)) {
				$lastblocktime = $row["blocktime"];
			}
			if (!isset($lasteverpaid)) {
				$lasteverpaid = $row["everpaid"];
			}

			$thisctime = strtotime($row["time"]);

			if (($lastctime) && (($thisctime - $lastctime) > 2500)) {
				# repeat last row data... except with this date - 1 second
				if (strlen(strstr($pline,",")) > 0) {
					$tline = date("Y-m-d H:i:s",$thisctime-1).strstr($pline,",")."\n";
					print $tline; $tdata .= $tline;
				}

			}

			if ($lastblocktime != $row["blocktime"]) {
				$startbalance = $lastrowbalance;
				$lastblocktime = $row["blocktime"];
			}
			if ($row["everpaid"] != $lasteverpaid) {
				$startbalance -= ($row["everpaid"] - $lasteverpaid)>0? ($row["everpaid"] - $lasteverpaid):0;
				if ($startbalance < 0) { $startbalance = 0; }
				$lasteverpaid = $row["everpaid"];
			}

			$pline = $row["time"].",";
			$pline .= ($row["everpaid"]/100000000).",";
			$pline .= ($row["balance"]/100000000)+($row["everpaid"]/100000000).",";
			$pline .= ($row["credit"]/100000000)+($row["balance"]/100000000)+($row["everpaid"]/100000000).",";
			$pline .= ($startbalance/100000000)+($row["everpaid"]/100000000);
			print $pline."\n";
			$tdata .= $pline."\n";
			$lastrowbalance = $row["balance"];
			$lastctime = $thisctime;



		}

		set_stats_cache($link, 1, $query_hash, $tdata, 675);

		exit;

	}

	if ($cmd == "hashgraph") {



		$sql = "select gtime,COALESCE(hashrate,0) as hashrate from (select * from generate_series(to_timestamp(((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'$sstart seconds'::interval)::integer / 675) * 675)-43200), to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'$start seconds'::interval)::integer / 675) * 675), '$ressec seconds') as gtime) as gentime left join (select * from $psqlschema.stats_shareagg where server=$serverid and user_id=$user_id) as dstats on (dstats.time = gentime.gtime);";

		$query_hash = hash("sha256", $sql);
		$cacheddata = get_stats_cache($link, 2, $query_hash);
		if ($cacheddata != "") {
			# this output is cached!
			print $cacheddata;
			exit;
		}



		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);

		$an = 0;
		$an2 = 0;
		$lav = 0;

		for($i=0;$i<64;$i++) { $ta[$i] = 0; $ta2[$i] = 0; }
		for($i=0;$i<128;$i++) { $lagavg3[$i] = 0; $lagavg12[$i] = 0; }

		$tdata =  "date,".$ressec." seconds,3 hour,12 hour\n";
		print $tdata;

		for($ri = 0; $ri < $numrows+32; $ri++) {
			if ($ri < $numrows) {
				$row = pg_fetch_array($result, $ri);
				$ta[$an] = $row["hashrate"]; $an++; if ($an == 16) { $an = 0; } $av = 0; for($i=0;$i<16;$i++) { $av += $ta[$i]; } if ($ri > 16) {	$av = $av / 16; } else { $av = ""; }
				$ta2[$an2] = $row["hashrate"]; $an2++; if ($an2 == 64) { $an2 = 0; } $av2 = 0; for($i=0;$i<64;$i++) { $av2 += $ta2[$i]; } if ($ri > 64) {	$av2 = $av2 / 64; } else { $av2 = ""; }
				list($datex) = explode("+", $row["gtime"]);
				$lagavg3[$lav] = round($av/1000000,2);
				$lagavg12[$lav] = round($av2/1000000,2);
			} else {
				unset($row);
				$lagavg3[$lav] = "";
				$lagavg12[$lav] = "";
			}
			if ($ri > 64) {
				if (isset($row)) {
					$lagavg675[$lav] = $datex.",".round($row["hashrate"]/1000000,2);
				}
				if ($ri > 64+32) {

					$lavx = $lav - 32; if ($lavx < 0) { $lavx+=128; }
					$lavy = $lav - 24; if ($lavy < 0) { $lavy+=128; }
					$lavz = $lav - 0; if ($lavz < 0) { $lavz+=128; }

					$tline = $lagavg675[$lavx].",".$lagavg3[$lavy].",".$lagavg12[$lavz]."\n";
					print $tline; $tdata .= $tline;
				}
			}
			$lav++; if ($lav == 128) { $lav = 0; }
		}

		set_stats_cache($link, 2, $query_hash, $tdata, 675);

		exit;
	}


?>
