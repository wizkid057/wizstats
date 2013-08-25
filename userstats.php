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

require_once 'hashrate.php';

# TODO: Allow worker sub-names
if (!isset($_SERVER['PATH_INFO'])) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>Error:</B> No username specified in URL path  Please try again.</FONT><BR>";
	print_stats_bottom();
	exit;
}

$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

if (pg_connection_status($link) != PGSQL_CONNECTION_OK) {
	pg_close($link);
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if (pg_connection_status($link) != PGSQL_CONNECTION_OK) {
		print_stats_top();
		print "<BR><FONT COLOR=\"RED\"><B>Error:</B> Unable to establish a connection to the stats database.  Please try again later. If this issue persists, please report it to the pool operator.</FONT><BR>";
		print_stats_bottom();
		exit;
	}
}



$givenuser = substr($_SERVER['PATH_INFO'],1,strlen($_SERVER['PATH_INFO'])-1);
$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

$sql = "select id from public.users where keyhash='$bits' order by id asc limit 1";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);

if (!$numrows) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>Error:</B> Username <I>$givenuser</I> not found in database.  Please try again later., as the stats server is probably just overloaded. If this issue persists for several hours, please report it to the pool operator.</FONT><BR>";
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
	if (isset($mybal["everpaid"])) { $everpaid = $mybal["everpaid"]; } else { $everpaid = 0; }
	$balupdate = $mybal["last_balance_update"];
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
if (isset($balanacesjsondecSM[$givenuser])) { $mybalSM = $balanacesjsondecSM[$givenuser]; }

if (isset($mybalSM)) {
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
} else {
	$smppsL1 = "";
	$smppsL2 = "";
	$smppsL3 = "";
	$smppsL4 = "";
}

$nickname = get_nickname($link,$user_id);

if ($nickname != "") {
	print "<H2><I>$nickname</I> <small> - $givenuser</small></H2>";
} else {
	print "<h2>$givenuser</h2>";
}


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
	$u16avghash = get_stats_cache($link, 111, $query_hash);
} else {

	$hashrate_info = get_hashrate_stats($link, $givenuser, $user_id);

	$pdata = "<TABLE class=\"userstatshashrate\">";
	$pdata .= "<THEAD><TR><TH WIDTH=\"34%\"></TH><TH WIDTH=\"33%\">Hashrate Average</TH><TH WIDTH=\"33%\"><span title=\"Weighted shares are the number of shares accepted by the pool multiplied by the difficulty of the work that was given.  This number is essentially the equivilent difficulty 1 shares submitted to the pool.\" style=\"border-bottom: 1px dashed #888888\">Weighted Shares</span></TH></TR></THEAD>";

	$oev = "even";

	foreach ($hashrate_info["intervals"] as $interval)
	{
		$hashrate_info_for_interval = $hashrate_info[$interval];

		$interval_name = $hashrate_info_for_interval["interval_name"];
		$hashrate = $hashrate_info_for_interval["hashrate"];
		$shares = $hashrate_info_for_interval["shares"];

		$pdata .= "<TR class=\"userstats$oev\"><TD>$interval_name</TD><TD style=\"text-align: right;\">" . prettyHashrate($hashrate) . "</TD><TD style=\"text-align: right;\">" . $shares . "</TD></TR>";

		$oev = $oev=="even"?$oev="odd":$oev="even";
	}

	$pdata .= "</TABLE>";

	print $pdata;

	$u16avghash = $hashrate_info[10800]["hashrate"];

	set_stats_cache($link, 11, $query_hash, $pdata, 30);
	set_stats_cache($link, 111, $query_hash, $u16avghash, 30);
}


if (0==1) {
# Reject data
$ulockid = $user_id + 20000007;
$sql = "select reason,count(*) as reject_count from public.shares where server=$serverid and user_id=$user_id and our_result!=true and time > NOW()-'1 hour'::interval group by reason order by reject_count;";
$query_hash = hash("sha256", $sql);
$rejecttable = get_stats_cache($link, 10, $query_hash);
if ($rejecttable != "") {
	print $rejecttable;
} else {

	$lsql = "select pg_try_advisory_lock($ulockid) as l";
	$result = pg_exec($link, $lsql); $row = pg_fetch_array($result, 0);
	$lock = $row["l"];
	if ($lock == "f") {

		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		$pdata = "<TABLE class=\"userstatsrejects\" id=\"rejectdata\">";
		$pdata .= "<THEAD><TR><TH STYLE=\"font-size: 70%;\" id=\"expandarea\"></TH><TH><SPAN title=\"Rejected share counts here are absolute counts and are not weighted.\" style=\"border-bottom: 1px dashed #888888\">Rejected Shares</span></TH></TR></THEAD>";
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
			$pdata .= "<TR class=\"userstatseven\"><TD>1-hour Total</TD><TD class=\"rtnumbers\">$t</TD></TR>";
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
			$pdata .= "<TR class=\"userstatseven\"><TD>1-hour Total</TD><TD class=\"rtnumbers\">0</TD></TR>";
			$pdata .= "</TABLE>";
		}
		print $pdata;
		set_stats_cache($link, 10, $query_hash, $pdata, 300);
		$sql = "select pg_advisory_unlock($ulockid) as l";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	}
}

}
print "<BR><BR>";


if (isset($_GET["timemachine"])) {
	$secondsback = 5184000;
} else {
	$secondsback = 604800;
}

print "<div id=\"ugraphdiv2\" style=\"width:750px; height:375px;\"></div>";
print "<INPUT TYPE=\"BUTTON\" onClick=\"showmax();\" VALUE=\"Toggle Graphing of Maximum Reward\"><BR>";
print "<div id=\"ugraphdiv3\" style=\"width:750px; height:375px;\"></div>";

if (!isset($_GET["timemachine"])) {
	print "<A HREF=\"?timemachine=1\">(Click for up to 60 days of hashrate/balance data)</A><BR>";
}

# script for dygraphs
print "<script type=\"text/javascript\">

	var blockUpdateA = 0;
	var blockUpdateB = 0;

	g2 = new Dygraph(document.getElementById(\"ugraphdiv2\"),\"$givenuser?cmd=hashgraph&start=0&back=$secondsback&res=1\",{ 
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
	document.getElementById(\"ugraphdiv3\"),\"$givenuser?cmd=balancegraph&start=0&back=$secondsback&res=1\",{ 
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

	$query_hash = hash("sha256", "userstats.php latest payouts for $givenuser with id $user_id and latest everpaid of $everpaid");
	$latestpayouts = get_stats_cache($link, 12, $query_hash);
	if ($latestpayouts != "") {
		print $latestpayouts;
	} else {
		# walk the blocklist back a ways to check for payouts
		$blockjsondec = json_decode(file_get_contents("/var/lib/eligius/$serverid/blocks/latest.json"),true);
		$myblockdata = $blockjsondec[$givenuser];
		$lastep = $everpaid;
		$lastblock = substr(readlink("/var/lib/eligius/$serverid/blocks/latest.json"),0,-5);
		if (strlen($lastblock) < 64) { $lastblock = "latest"; }
		$thisep = $lastep;

		$maxlook = 5000;
		$maxtable = 8;
		$xdata = "";
		$oev = "even";
		while(($maxlook) && ($maxtable) && ($thisep > 0)) {
			$paidblock = $lastblock;
			$forcetype = "";
			if ((isset($blockjsondec[""]["roundend"])) && ($blockjsondec[""]["roundend"] > 0)) {
				$paydate = date("Y-m-d H:i",$blockjsondec[""]["roundend"]);
				$paydateshort = date("Y-m-d",$blockjsondec[""]["roundend"]);
			} else {
				# get time from manual send creation time
				$paydatectime = filectime("/var/lib/eligius/$serverid/blocks/".($lastblock).".json");
				if ($paydatectime > 0) {
					$paydate = date("Y-m-d H:i",$paydatectime); 
					$paydateshort = date("Y-m-d",$paydatectime); 
					$forcetype = "S";
				} else {
					$paydate = "Unknown";
				}
			}

			$lastblock = $blockjsondec[""]["mylastblk"];
			if (!isset($blockjsondec[""]["mylastblk"])) { $maxlook = 1; }
			$oldblockjsondec = $blockjsondec;
			$blockjsondec = json_decode(file_get_contents("/var/lib/eligius/$serverid/blocks/".($lastblock).".json"),true); 
			if (isset($blockjsondec[$givenuser])) { $myblockdata = $blockjsondec[$givenuser]; } else { unset($myblockdata); }
			if (isset($myblockdata["everpaid"])) {
				$thisep = $myblockdata["everpaid"];
			} else {
				if (isset($blockjsondec[$givenuser])) {
					$thisep = 0;
				} else {
					if (isset($oldblockjsondec[$givenuser])) {
						# this user was either fully paid and old, then started mining again, or, they just started and the last block was their first payout ever.
						$thisep = 0;
						$forcetype = "O";
					}
				}
			}
			$maxlook--;
			if ($thisep < $lastep) {
				$paid = $lastep - $thisep;
				$paid = prettySatoshis($paid);
				if (strpos($paidblock,'_send') != false) {
					$type = "S";
				} else {
					$type = "G";
				}
				if ($forcetype != "") {
					$type = $forcetype;
				}
				if ($paidblock != "latest") {
					$type = "<A HREF=\"http://blockchain.info/search?search=".substr($paidblock,0,64)."\">$type</A>";
				}

				if ($forcetype != "O") {
					$xdata .= "<TR class=\"userstats$oev\"><TD title=\"$paidblock\">$paydate ($type)</TD><TD class=\"rtnumbers\">$paid</TD></TR>";
				} else {
					$xdata .= "<TR class=\"userstats$oev\"><TD>$paydateshort <small>and prior</small></TD><TD class=\"rtnumbers\">$paid</TD></TR>";
				}
				$oev = $oev=="even"?$oev="odd":$oev="even";
				$lastep = $thisep;
				$maxtable--;
			}
		}
		if ($xdata != "") {
			$pdata = "<table id=\"paymentlist\"><THEAD><TR><TH>Date (<SPAN title=\"G = Payout from coinbase/generation; S = Payout from normal send/sendmany\" style=\"border-bottom: 1px dashed #888888\">Type</SPAN>)</TH><TH>Amount</TH></TR></THEAD>$xdata</table>";
		} else {
			$pdata = "<BR>No data available.<BR>";
		}
		print $pdata;
		# cache this data for 24 hours. if the user is paid, the hash will change and invalidate this forcing a rebuild. genius!
		set_stats_cache($link, 12, $query_hash, $pdata, 3600*24);
	}
} else {
	print "<BR>No data available.<BR>";
}

print "All time total payout: ".prettySatoshis($everpaid);
print "<BR><BR>";


if ($savedbal) {

	print "<B>Estimated Position in Payout Queue</B><BR>";
	$payoutqueue = file_get_contents("/var/lib/eligius/$serverid/payout_queue.txt");
	print "<span style=\"font-size: 0.8em\">";
	if ((strpos($payoutqueue,$givenuser) == false) && (substr($payoutqueue,0,strlen($givenuser)) != $givenuser)) {
		$diff = 16777216 - $savedbal;
		if ($diff < 0) { $diff = 0; }
		print "Approximately ".prettySatoshis($diff)." remaining to enter payout queue.";

		if (($u16avghash == 0) && (isset($balupdate))) {
			$timetoqueue = (3600*24*7) - (time() - $balupdate);
			print " If you remain inactive";
			if ((isset($lec)) && ($lec > 0)) {
				print ", and the pool does not pay towards any of your shelved shares,";
			}
			print " then you will enter the payout queue, due to inactivity, in approximately ".prettyDuration($timetoqueue).".";
		}

		if ($u16avghash > 0) {
			$sql = "select id,(pow(10,((29-hex_to_int(substr(encode(solution,'hex'),145,2)))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where server=$serverid and time < (select time from $psqlschema.stats_shareagg where server=$serverid order by id desc limit 1) and our_result=true order by id desc limit 1;";
	                $result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	                $netdiff = $row["network_difficulty"];

			$shares = $diff / (2500000000/$netdiff);
			$stime = $shares / ($u16avghash / 4294967296);
			$netdiff = round($netdiff,2);
			print " Maintaining your 3 hour hashrate average, this will take at least another ".prettyDuration($stime). " at current network difficulty of $netdiff.";
		}

	} else {
		# add up balances and see where we end up.
		$tb = 0; $bc = 0; $overflow = 0;
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $payoutqueue) as $pquser){
			if ($pquser != $givenuser) {
				$tb += $balanacesjsondec[$pquser]["balance"];
				while ($tb > 2500000000) {
					$tb = $tb - 2500000000;
					$bc++;
				}
			} else {
				if (($tb+$balanacesjsondec[$pquser]["balance"]) > 2500000000) {
					$overflow = 1;
				}
				break;
			}
		}
		if (($bc == 0) && (!$overflow)) {
			$aheadtext = "Less than 25 BTC ahead in queue";
		} else {
			if ($overflow) {
				$aheadtext = prettySatoshis($tb+(2500000000*($bc)))." ".(($tb+(2500000000*($bc+1)))==1?"is":"are")." ahead in queue, but our payout is more than the remaining block reward of ".prettySatoshis((2500000000)-$tb);
				$bc++;
			} else {
				$aheadtext = prettySatoshis($tb+(2500000000*($bc)))." ".(($tb+(2500000000*($bc+1)))==1?"is":"are")." ahead in queue";
			}
		}
		if ($bc == 0) {
			$delay = "in our next block";
		} else {
			$delay = "after a $bc block delay";
		}
		print $aheadtext.", putting this user's payout $delay.<BR><SMALL style=\"font-size: 70%\"><I>Note: This is constantly changing. See <A HREF=\"http://eligius.st/~wizkid057/newstats/payoutqueue.php#$givenuser\">the payout queue</A>.</I></SMALL>";
	}
	print "</span>";
}

print "</div>";

print "<BR><SMALL>(The data on this page is cached and updated periodically, generally about 30 seconds for the short-timeframe hashrate numbers, balances, and rejected shares data; and about 675 seconds for the graphs, longer-timeframe hashrate numbers, and other datas.</SMALL><BR>";
print_stats_bottom();

?>
