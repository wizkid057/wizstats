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
			order by stats_balances.time asc limit 1) order by id desc limit 1) order by time desc limit 1;";

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

		$wherein = get_wherein_list_from_worker_data($worker_data);
		$sql = "select *,date_part('epoch',time) as ctime from wizkid057.stats_shareagg where server=$serverid and user_id in $wherein and time > NOW()-'$sstart seconds'::interval-'43200 seconds'::interval order by time desc;";

		$query_hash = hash("sha256", $sql);
		$cacheddata = get_stats_cache($link, 2, $query_hash);
		if ($cacheddata != "") {
			# this output is cached!
			print $cacheddata;
			exit;
		}

		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);

		$data = array();
		$firstctime = pow(2,32);
		$lastctime = 0;
		$workercount = 0;
		$activeworkers = array();
		for($ri=0;$ri<$numrows;$ri++) {
			# make associative array for data based on time and userid
			$row = pg_fetch_array($result, $ri);
			$data[$row["ctime"]][$row["user_id"]] = $row;
			if ($row["ctime"] < $firstctime) { $firstctime = $row["ctime"]; }
			if ($row["ctime"] > $lastctime) { $lastctime = $row["ctime"]; }

			# count active workers in this data
			if (count($data[$row["ctime"]]) > $workercount) { $workercount = count($data[$row["ctime"]]); }
			$activeworkers[$row["user_id"]] = 1;
		}

		$gline = 0;
		$gdata = array();

		$buf = "";

		$buf .= "date";
		$wc = 1;
		if ($workercount > 1) {
			$workerlist = array();
			$workerlistR = array();
			$wc = 0;
			foreach ($worker_data as $wid => $wname) {
				if ($activeworkers[$wid] == 1) {
					$wname = str_replace(",", ".", $wname);
					$wname = str_replace(" ", "_", $wname);
					$buf .=  ",$wname";
					$workerlist[$wc] = $wid;
					$workerlistR[$wid] = $wc;
					$wc++;
				}
			}
		} else {
			$workerlist[0] = $user_id;
			$workerlistR[$user_id] = 0;
		}

		$buf .=  ",".$ressec." seconds,3 hour,12 hour\n";
		$workertemp = array();
		for($t=$firstctime;$t<=$lastctime;$t+=675) {
			# if no data then hashrate is assumed 0
			for($i=0;$i<$wc;$i++) { $workertemp[$i] = 0; }
			$th = 0;
			if ( (isset($data[$t])) && (count($data[$t]) > 0) ) {
				foreach ($data[$t] as $id => $row) {
					$th += $row["hashrate"];
					$workertemp[$workerlistR[$id]] = $row["hashrate"];
				}
			}

			$gdata[$gline] = array($t, $th, $workertemp);
			$gline++;
		}


		for($i=0;$i<$gline;$i++) {

			$avg3 = ""; $avg12 = "";
			if (($i > 16) && ($i < ($gline - 8))) {
				$avg3 = 0;
				for($j=0;$j<16;$j++) {
					$avg3 += $gdata[$i-$j+8][1];
				}
				$avg3 = $avg3 / 16;
			}
			if (($i > 64) && ($i < ($gline - 32))) {
				$avg12 = 0;
				for($j=0;$j<64;$j++) {
					$avg12 += $gdata[$i-$j+32][1];
				}
				$avg12 = $avg12 / 64;
			}

			if ($avg3 != "") { $avg3 = round($avg3/1000000,3); }
			if ($avg12 != "") { $avg12 = round($avg12/1000000,3); }
			$buf .=  date("Y-m-d H:i:s",$gdata[$i][0]);
			$lastlinedate = $gdata[$i][0];
			if ($wc > 1) {
				for($j=0;$j<$wc;$j++) { 
					if ($gdata[$i][2][$j]) {
						$buf .=  ",".round($gdata[$i][2][$j]/1000000,3); 
					} else {
						if ($gdata[$i-1][2][$j] > 0) {
							$buf .= ",0";
						} else {
							if (($i<($gline-2)) && ($gdata[$i+1][2][$j] > 0)) {
								$buf .= ",0";
							} else {
								$buf .= ",";
							}
						}
					}
				}
			}
			$buf .=  ",".round($gdata[$i][1]/1000000,3).",".$avg3.",".$avg12;
			$buf .=  "\n";

		}

		if ($lastlinedate < (time()-(675*2))) {
			$buf .= date("Y-m-d H:i:s",time());
			if ($wc > 1) {
				for($j=0;$j<$wc;$j++) { 
					$buf .= ",";
				}
			}
			$buf .= ",,,\n";
		}

		print $buf;
		set_stats_cache($link, 2, $query_hash, $buf, 675);
		exit;
	}


?>
