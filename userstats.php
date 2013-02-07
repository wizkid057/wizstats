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


# TODO: Allow worker sub-names

if (!isset($_SERVER['PATH_INFO'])) {
	print "Error: Specify username in path\n";
	exit;
}

require_once 'includes.php';
$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");


$givenuser = substr($_SERVER['PATH_INFO'],1,strlen($_SERVER['PATH_INFO'])-1);
$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

$sql = "select id from public.users where keyhash='$bits' order by id asc limit 1";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);
if (!$numrows) {
	print "Error: Username <I>$givenuser</I> not found in database.  Please try again later.".$_SERVER['PATH_INFO'];
	exit;
}

$row = pg_fetch_array($result, 0);
$user_id = $row["id"];



if (isset($_GET["cmd"])) {
	$cmd = $_GET["cmd"];

	$res   = isset($_GET["res"])?$_GET["res"]:1;
	$start = isset($_GET["start"])?$_GET["start"]:0;
	$back  = isset($_GET["back"])?$_GET["back"]:86400;
	if (($res < 1) || ($res > 128)) { $res = 1; }
	if (($start < 0)) { $start = 0; }
	if (($back < 675)) { $back = 675; }
	$sstart = round(($back+$start)/675)*675;
	$ressec = $res * 675;


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

}


$balanacesjson = file_get_contents("/var/lib/eligius/$serverid/balances.json");
$balanacesjsondec = json_decode($balanacesjson,true);
$mybal = $balanacesjsondec[$givenuser];

if ($mybal) {
	$bal = $mybal["balance"];
	$ec = $mybal["credit"];
	$datadate = $mybal["newest"];
	$lbal = $bal - $mybal["included_balance_estimate"];
	$lec = $ec - $mybal["included_credit_estimate"];
} else {
	# fall back to sql
	$sql = "select * from $psqlschema.stats_balances where server=$serverid and user_id=$user_id order by time desc limit 1";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	if (!$numrows) {
		$bal = "N/A"; $cbe = "N/A"; $ec = "N/A"; $datadate = "N/A"; $lbal = "N/A";
	} else {
		$row = pg_fetch_array($result, 0);
		$bal = $row["balance"];
		$ec = $row["credit"];
		$lbal = "N/A";
		$datadate = $row["time"];
	}
}

$balanacesjsonSM = file_get_contents("/var/lib/eligius/$serverid/smpps_lastblock.json");
$balanacesjsondecSM = json_decode($balanacesjsonSM,true);
$mybalSM = $balanacesjsondecSM[$givenuser];

if ($mybalSM) {
	$smppsec = $mybalSM["credit"];
} else {
	$smppsec = 0;
}




### TODO: CONSIDER ORPHANS HERE ?
### actually, maybe the coinbaser does this? not sure... need to catch it...
### TODO: Non hardcoded path...

$cbal = $bal - $lbal;
$cec = $ec - $lec;

$xbal = $bal - $cbal;
$xec = $ec - $cec - $smppsec;


if ($cbal > 0) { $cbalt = "+".prettySatoshis($cbal); }
else { $cbalt = prettySatoshis($cbal); }
if ($cec > 0) { $cect = "+".prettySatoshis($cec); }
else { $cect = prettySatoshis($cec); }

$xbal = prettySatoshis($xbal);
$xec = prettySatoshis($xec);
$bal = prettySatoshis($bal);
$ec = prettySatoshis($ec-$smppsec);

$titleprepend = "($bal) $givenuser - ";
print_stats_top();

if ($smppsec) {
	$snice = prettySatoshis($smppsec);
	$smppsL1 = "<TD>Old <A HREF=\"http://eligius.st/wiki/index.php/Shared_Maximum_PPS\">SMPPS</A> Extra Credit</TD>";
	$smppsL2 = "<TD style=\"text-align: right; font-size: 70%;\">$snice</TD>";
	$smppsL3 = "<TD style=\"text-align: right; font-size: 70%;\">0.00000000 BTC</TD>";
	$smppsL4 = "<TD style=\"text-align: right; font-size: 70%;\">$snice</TD>";
}

print "<H2>$givenuser</H2>";
print "<TABLE BORDER=1>";
print "<TR><TD></TD><TD>Unpaid Balance</TD><TD>Shelved Shares (<A HREF=\"http://eligius.st/wiki/index.php/Capped_PPS_with_Recent_Backpay\">CPPSRB</A>)</TD>$smppsL1</TR>";
print "<TR><TD>As of last block*: </TD><TD style=\"text-align: right;\">$xbal</TD><TD style=\"text-align: right; font-size: 80%;\">$xec</TD>$smppsL2</TR>";
print "<TR><TD>Estimated Change: </TD><TD style=\"text-align: right;\">$cbalt</TD><TD style=\"text-align: right; font-size: 80%;\">$cect</TD>$smppsL3</TR>";
print "<TR><TD>Estimated Total: </TD><TD style=\"text-align: right;\">$bal</TD><TD style=\"text-align: right; font-size: 80%;\">$ec</TD>$smppsL4</TR>";
print "</TABLE>";


# 3 hour hashrate
# FIX FOR BAD HASHRATE BUG 091512
$sql = "select (sum(accepted_shares)*pow(2,32))/10800 as avghash from $psqlschema.stats_shareagg where server=$serverid and user_id=$user_id and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'3 hours'::interval)::integer / 675::integer) * 675::integer)";
$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
$u16avghash = isset($row["avghash"])?$row["avghash"]:0;

# 22.5 minute hashrate
# FIX FOR BAD HASHRATE BUG 091512
$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash from $psqlschema.stats_shareagg where server=$serverid and user_id=$user_id and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
$u2avghash = isset($row["avghash"])?$row["avghash"]:0;

print "3 hour average hashrate: ".prettyHashrate($u16avghash)."<BR>\n";
print "22.5 minute average hashrate: ".prettyHashrate($u2avghash)."<BR>\n";


$cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
$cppsrbjsondec = json_decode($cppsrbjson,true);
$mycppsrb = $cppsrbjsondec[$givenuser];
$globalccpsrb = $cppsrbjsondec[""];
$my_shares = $mycppsrb["shares"];



for($i=128;$i<257;$i*=2) {
	print "Instant $i second average hashrate: " . prettyHashrate(($my_shares[$i] * 4294967296)/$i) . "<BR>";
}



if (isset($_GET["timemachine"])) {
	$secondsback = 5184000;
} else {
	$secondsback = 604800;
}



print "<div id=\"graphdiv2\" style=\"width:750px; height:375px;\"></div>";


print "<INPUT TYPE=\"BUTTON\" onClick=\"showmax();\" VALUE=\"Toggle Graphing of Maximum Reward\"><BR>";
print "<div id=\"graphdiv3\" style=\"width:750px; height:375px;\"></div>";

if (!isset($_GET["timemachine"])) {
	print "<A HREF=\"?timemachine=1\">(Click for up to 60 days of hashrate/balance data)</A><BR>";
}


print "<script type=\"text/javascript\">

	var blockUpdateA = 0;
	var blockUpdateB = 0;

  g2 = new Dygraph(
    document.getElementById(\"graphdiv2\"),
    \"$givenuser?cmd=hashgraph&start=0&back=$secondsback&res=1\",
   	{ strokeWidth: 2.25,
	'675 seconds': { fillGraph: true, strokeWidth: 1.5 },
	labelsDivStyles: { border: '1px solid black' },
	title: 'Hashrate Graph ($givenuser)',
	xlabel: 'Date',
	ylabel: 'Mh/sec',
	animatedZooms: true,
	drawCallback: function(dg, is_initial) {
                if (is_initial) {
				var rangeA = g2.xAxisRange();
				g3.updateOptions( { dateWindow: rangeA } );
		} else {
			if (!blockUpdateA) {
				blockUpdateB = 1;
				var rangeA = g2.xAxisRange();
				g3.updateOptions( { dateWindow: rangeA } );
				blockUpdateB = 0;
			}
		}
           }

	}

  );


  var mrindex = 0;
var mrhidden = 1;
  g3 = new Dygraph(
    document.getElementById(\"graphdiv3\"),
    \"$givenuser?cmd=balancegraph&start=0&back=$secondsback&res=1\",
	{ strokeWidth: 2.25,
	fillGraph: true,
	labelsDivStyles: { border: '1px solid black' },
	title: 'Balance Graph ($givenuser)',
	xlabel: 'Date',
	ylabel: 'BTC',
	animatedZooms: true,
	drawCallback: function(dg, is_initial) {
                if (is_initial) {
			mrindex = dg.indexFromSetName(\"maximum reward\") - 1;
	             dg.setVisibility(mrindex, 0);
				var rangeB = g3.xAxisRange();
				g2.updateOptions( { dateWindow: rangeB } );
		} else {
			if (!blockUpdateB) {
				blockUpdateA = 1;
				var rangeB = g3.xAxisRange();
				g2.updateOptions( { dateWindow: rangeB } );
				blockUpdateA = 0;
			}
		}
           }

	}
  );

	var showmax = function() {
		if (mrhidden) {
			g3.setVisibility(mrindex, 1);
			mrhidden = 0;
		} else {
			g3.setVisibility(mrindex, 0);
			mrhidden = 1;
		}
	}

</script>
";


if (isset($_GET["wizdebug"])) {


$latest_chunk = $globalccpsrb['share_log_top_chunk'];

#var_export ($globalccpsrb['share_log_top_chunk']);

#print "<PRE>";

#var_dump($mycppsrb);

$my_share_log = $mycppsrb["share_log"];

#var_dump($my_shares);
#var_dump($my_share_log);



if (isset($my_share_log["total"])) {

	if ($my_share_log["encoding"] == "hex+gzip+base64") {
		$d = base64_decode($my_share_log["data"]);
		$data = compatible_gzinflate($d);
	}
	if ($my_share_log["encoding"] == "hex") {
		$date = $my_share_log["data"];
	}


$x = 0;
$dgd = "Chunk,Shares\\n";
$firstchunk = $my_share_log["first_chunk"];
$c = $firstchunk;
for($i=0;$i<$c;$i++) { $dgd .= "$i,0\\n"; }
while($x < strlen($data)) {

	$a = hexdec(substr($data,$x,2))*256;
	$a += hexdec(substr($data,$x+2,2));

	$x+=4;

	$dgd .= "$c,$a\\n";
	$c++;

}

if ($c < $latest_chunk) {
for($i=$c;$i<$latest_chunk;$i++) { $dgd .= "$i,0\\n"; }
}

print "<BR>Share log visualization (empty areas indicate either no mining or already paid, but not yet pruned, shares)<BR><div id=\"labels4\"  style=\"width:750px; height:40px;\"></div><div id=\"graphdiv4\" style=\"width:750px; height:100px;\"></div>";

print "<script type=\"text/javascript\">

function barChartPlotter(e) {
  var ctx = e.drawingContext;
  var points = e.points;
  var y_bottom = e.dygraph.toDomYCoord(0);  // see http://dygraphs.com/jsdoc/symbols/Dygraph.html#toDomYCoord
 
  // This should really be based on the minimum gap
  var bar_width = 2/3 * (points[1].canvasx - points[0].canvasx);
  ctx.fillStyle = e.color;
 
  // Do the actual plotting.
  for (var i = 0; i < points.length; i++) {
    var p = points[i];
    var center_x = p.canvasx;  // center of the bar
 
    ctx.fillRect(center_x - bar_width / 2, p.canvasy,
        bar_width, y_bottom - p.canvasy);
    ctx.strokeRect(center_x - bar_width / 2, p.canvasy,
        bar_width, y_bottom - p.canvasy);
  }
}

  g4 = new Dygraph(
    document.getElementById(\"graphdiv4\"),
    \"$dgd\",
        { plotter: barChartPlotter, labelsDiv: \"labels4\" } );

</script>
";


}

#print "</PRE>";

}

print_stats_bottom();

?>
