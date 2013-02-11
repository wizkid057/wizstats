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


require_once 'includes.php';

# TODO: Allow worker sub-names
if (!isset($_SERVER['PATH_INFO'])) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>Error:</B> No username specified in URL path  Please try again.</FONT><BR>";
	print_stats_bottom();
	exit;
}

$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

$givenuser = substr($_SERVER['PATH_INFO'],1,strlen($_SERVER['PATH_INFO'])-1);
$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

$sql = "select id from public.users where keyhash='$bits' order by id asc limit 1";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);
if (!$numrows) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>Error:</B> Username <I>$givenuser</I> not found in database.  Please try again later. If this issue persists, please report it to the pool operator.</FONT><BR>";
	print_stats_bottom();

	exit;
}

$row = pg_fetch_array($result, 0);
$user_id = $row["id"];


if (isset($_GET["cmd"])) {
	include("userstats_subcmd.php");
}

$cppsrbloaded = 0;

$balanacesjson = file_get_contents("/var/lib/eligius/$serverid/balances.json");
$balanacesjsondec = json_decode($balanacesjson,true);
$mybal = $balanacesjsondec[$givenuser];

if ($mybal) {
	if (isset($mybal["balance"])) {
		$bal = $mybal["balance"];
	} else {
		$bal = 0;
	}
	if (isset($mybal["credit"])) {
		$ec = $mybal["credit"];
	} else {
		$ec = 0;
	}
	$datadate = $mybal["newest"];
	if (isset($mybal["included_balance_estimate"])) {
		$lbal = $bal - $mybal["included_balance_estimate"];
	} else {
		$lbal = $bal;
	}
	if (isset($mybal["included_credit_estimate"])) {
		$lec = $ec - $mybal["included_credit_estimate"];
	} else {
		$lec = $ec;
	}
	$everpaid = $mybal["everpaid"];

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
		$everpaid = $row["everpaid"];
	}
}

$balanacesjsonSM = file_get_contents("/var/lib/eligius/$serverid/smpps_lastblock.json");
$balanacesjsondecSM = json_decode($balanacesjsonSM,true);
$mybalSM = $balanacesjsondecSM[$givenuser];

if ($mybalSM) {
	# SMPPS credit needed to be halved for the pool to be statistically viable
	$smppsec = $mybalSM["credit"]; 
	$smppshalf = $mybalSM["credit"]/2;
	$smppsec -= $smppshalf;
} else {
	$smppsec = 0;
}


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
$savedbal = $bal;
$bal = prettySatoshis($bal);
$ec = prettySatoshis($ec-$smppsec);

$titleprepend = "($bal) $givenuser - ";
print_stats_top();

if ($smppsec) {
	$snice = prettySatoshis($smppsec);
	$smppsL1 = "<TH>Old <A HREF=\"http://eligius.st/wiki/index.php/Shared_Maximum_PPS\">SMPPS</A> Extra Credit</TH>";
	$smppsL2 = "<TD style=\"text-align: right; font-size: 70%;\">$snice</TD>";
	$smppsL3 = "<TD style=\"text-align: right; font-size: 70%;\">0.00000000 BTC</TD>";
	$smppsL4 = "<TD style=\"text-align: right; font-size: 70%;\">$snice</TD>";
}

print "<H2>$givenuser</H2>";

print "<div id=\"userstatsmain\">";
print "<TABLE class=\"userstatsbalance\">";
print "<THEAD><TR><TH></TH><TH>Unpaid Balance</TH><TH>Shelved Shares (<A HREF=\"http://eligius.st/wiki/index.php/Capped_PPS_with_Recent_Backpay\">CPPSRB</A>)</TH>$smppsL1</TR></THEAD>";
print "<TR class=\"userstatsodd\"><TD>As of last block: </TD><TD style=\"text-align: right;\">$xbal</TD><TD style=\"text-align: right; font-size: 80%;\">$xec</TD>$smppsL2</TR>";
print "<TR class=\"userstatseven\"><TD>Estimated Change: </TD><TD style=\"text-align: right;\">$cbalt</TD><TD style=\"text-align: right; font-size: 80%;\">$cect</TD>$smppsL3</TR>";
print "<TR class=\"userstatsodd\"><TD>Estimated Total: </TD><TD style=\"text-align: right;\">$bal</TD><TD style=\"text-align: right; font-size: 80%;\">$ec</TD>$smppsL4</TR>";
print "</TABLE>";

$query_hash = hash("sha256", "userstats.php hashrate table for $givenuser with id $user_id");
$hashratetable = get_stats_cache($link, 11, $query_hash);
if ($hashratetable != "") {
	print $hashratetable;
} else {

	# 3 hour hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/10800 as avghash,sum(accepted_shares) as share_total from $psqlschema.stats_shareagg where server=$serverid and user_id=$user_id and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'3 hours'::interval)::integer / 675::integer) * 675::integer)";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$u16avghash = isset($row["avghash"])?$row["avghash"]:0;
	$u16shares = isset($row["share_total"])?$row["share_total"]:0;

	# 22.5 minute hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash,sum(accepted_shares) as share_total from $psqlschema.stats_shareagg where server=$serverid and user_id=$user_id and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$u2avghash = isset($row["avghash"])?$row["avghash"]:0;
	$u2shares = isset($row["share_total"])?$row["share_total"]:0;

	# instant hashrates from CPPSRB
	$cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
	$cppsrbjsondec = json_decode($cppsrbjson,true);
	$mycppsrb = $cppsrbjsondec[$givenuser];
	$globalccpsrb = $cppsrbjsondec[""];
	$cppsrbloaded = 1;
	$my_shares = $mycppsrb["shares"];


	$pdata = "<TABLE class=\"userstatshashrate\">";
	$pdata .= "<THEAD><TR><TH WIDTH=\"34%\"></TH><TH WIDTH=\"33%\">Hashrate Average</TH><TH WIDTH=\"33%\"><span title=\"Weighted shares are the number of shares accepted by the pool multiplied by the difficulty of the work that was given.  This number is essentially the equivilent difficulty 1 shares submitted to the pool.\" style=\"border-bottom: 1px dashed #cccccc\">Weighted Shares</span></TH></TR></THEAD>";
	$pdata .= "<TR class=\"userstatseven\"><TD>3 hours</TD><TD style=\"text-align: right;\">".prettyHashrate($u16avghash)."</TD><TD style=\"text-align: right;\">$u16shares</TD></TR>";
	$pdata .= "<TR class=\"userstatsodd\"><TD>22.5 minutes</TD><TD style=\"text-align: right;\">".prettyHashrate($u2avghash)."</TD><TD style=\"text-align: right;\">$u2shares</TD></TR>";
	$oev = "even";
	for($i=256;$i>127;$i=$i/2) {
		$pdata .= "<TR class=\"userstats$oev\"><TD>$i seconds</TD><TD style=\"text-align: right;\">" . prettyHashrate(($my_shares[$i] * 4294967296)/$i) . "</TD><TD style=\"text-align: right;\">".(round($my_shares[$i]))."</TD></TR>";
		$oev = $oev=="even"?$oev="odd":$oev="even";
	}
	$pdata .= "</TABLE>";
	print $pdata;
	set_stats_cache($link, 11, $query_hash, $pdata, 30);

}

# Reject data
$sql = "select reason,count(*) as reject_count from public.shares where server=$serverid and user_id=$user_id and our_result!=true and time > NOW()-'3 hours'::interval group by reason order by reject_count;";
$query_hash = hash("sha256", $sql);
$rejecttable = get_stats_cache($link, 10, $query_hash);
if ($rejecttable != "") {
	print $rejecttable;
} else {
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	$pdata = "<TABLE class=\"userstatsrejects\" id=\"rejectdata\">";
	$pdata .= "<THEAD><TR><TH STYLE=\"font-size: 70%;\" id=\"expandarea\"></TH><TH><SPAN title=\"Rejected share counts here are absolute counts and are not weighted.\" style=\"border-bottom: 1px dashed #cccccc\">Rejected Shares</span></TH></TR></THEAD>";
	if ($numrows) {

		$t = 0;
		$rejectdetails = "";
		$toggles = "";
		$oev = "odd";
		for($ri = 0; $ri < $numrows; $ri++) {
			$row = pg_fetch_array($result, $ri);
			$count = $row['reject_count'];
			$t += $count;
			$reason = prettyInvalidReason($row['reason']);
			$rejectdetails .= "<TR class=\"userstats$oev\" id=\"rejectitem$ri\"><TD><FONT style=\"border-bottom: 1px dashed #999;\">$reason</FONT></TD><TD class=\"rtnumbers\">$count</TD></TR>";
			$toggles .= "\$('#rejectitem$ri').toggle();\n";
			$oev = $oev=="even"?$oev="odd":$oev="even";
		}
		$pdata .= "<TR class=\"userstatseven\"><TD>3-hour Total</TD><TD class=\"rtnumbers\">$t</TD></TR>";
		$pdata .= $rejectdetails;
		$pdata .= "</TABLE>";
		$pdata .= "<script language=\"javascript\">\n<!--\n";
		$pdata .= "\$(document).ready(function() {
				\$('#expandarea').click(function(){
					$toggles
					if (!\$('#rejectitem0').is(':hidden')) {
						\$('#expandarea').text('(Collapse Details)');
					} else {
						\$('#expandarea').text('(Expand Details)');
					}
					return false;
				});
				\$('#expandarea').css('cursor', 'pointer').click();;
			});\n";
		$pdata .= "\n--></script>\n";
	} else {
		$pdata .= "<TR class=\"userstatseven\"><TD>3-hour Total</TD><TD class=\"rtnumbers\">0</TD></TR>";
		$pdata .= "</TABLE>";
	}
	print $pdata;
	set_stats_cache($link, 10, $query_hash, $pdata, 60);
}

print "<BR><BR>";


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

# script for dygraphs
print "<script type=\"text/javascript\">

	var blockUpdateA = 0;
	var blockUpdateB = 0;

	g2 = new Dygraph(document.getElementById(\"graphdiv2\"),\"$givenuser?cmd=hashgraph&start=0&back=$secondsback&res=1\",{ 
		strokeWidth: 2.25,
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

	});

	var mrindex = 0;
	var mrhidden = 1;
	g3 = new Dygraph(
	document.getElementById(\"graphdiv3\"),\"$givenuser?cmd=balancegraph&start=0&back=$secondsback&res=1\",{ 
		strokeWidth: 2.25,
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
	});

	var showmax = function() {
		if (mrhidden) {
			g3.setVisibility(mrindex, 1);
			mrhidden = 0;
		} else {
			g3.setVisibility(mrindex, 0);
			mrhidden = 1;
		}
	}
</script>\n";

print "</div>";

# right side
print "<div id=\"userstatsright\">";


print "<B>Latest Payouts</B>";


if ($everpaid > 0) {

	# walk the blocklist back a ways to check for payouts
	$blockjsondec = json_decode(file_get_contents("/var/lib/eligius/$serverid/blocks/latest.json"),true);
	$myblockdata = $blockjsondec[$givenuser];
	$lastep = $everpaid;
	$lastblock = "latest";
	$thisep = $lastep;

	$maxlook = 500;
	$maxtable = 8;
	$xdata = "";
	$oev = "even";
	#print "$maxlook $maxtable $thisep\n";
	#print "opened $lastblock, opening ".$blockjsondec[""]["mylastblk"]."<BR>";
	while(($maxlook) && ($maxtable) && ($thisep > 0)) {
		$paidblock = $lastblock;
		$paydate = date("Y-m-d H:i",$blockjsondec[""]["roundend"]);
		$lastblock = $blockjsondec[""]["mylastblk"];
		$blockjsondec = json_decode(file_get_contents("/var/lib/eligius/$serverid/blocks/".($lastblock).".json"),true); $myblockdata = $blockjsondec[$givenuser];
		$thisep = $myblockdata["everpaid"];
		$maxlook--;
		if ($thisep < $lastep) {
			$paid = $lastep - $thisep;
			$paid = prettySatoshis($paid);
			if (strpos($paidblock,'_send') != false) {
				$type = "S";
			} else {
				$type = "G";
			}
			$xdata .= "<TR class=\"userstats$oev\"><TD title=\"$paidblock\">$paydate ($type)</TD><TD class=\"rtnumbers\">$paid</TD></TR>";
			$oev = $oev=="even"?$oev="odd":$oev="even";
			$lastep = $thisep;
			$maxtable--;
		}
	}
	if ($xdata != "") {
		print "<table id=\"paymentlist\"><THEAD><TR><TH>Date (<SPAN title=\"G = Payout from coinbase/generation; S = Payout from normal send/sendmany\" style=\"border-bottom: 1px dashed #cccccc\">Type</SPAN>)</TH><TH>Amount</TH></TR></THEAD>$xdata</table>";
	} else {
		print "<BR>No data available.<BR>";
	}
}
print "All time total payout: ".prettySatoshis($everpaid);
print "<BR><BR>";



print "</div>";



print "<BR><SMALL>(The data on this page is cached and updated periodically, generally about 30 seconds for the short-timeframe hashrate numbers, balances, and rejected shares data; and about 675 seconds for the graphs, longer-timeframe hashrate numbers, and other datas.</SMALL><BR>";
print_stats_bottom();

?>
