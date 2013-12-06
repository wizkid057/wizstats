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

require_once "includes.php";
#$bodytags = "onLoad=\"initShares();\"";
$ldmain = "-main";

print_stats_top();

$announce = file_get_contents("announce.txt");
if (strlen($announce) > 0) {
	print $announce."<BR><BR>";
}

?>


For individual stats, use http://eligius.st/~wizkid057/newstats/userstats.php/[your miner address] for individual stats.<BR>
For example, <A HREF="http://eligius.st/~wizkid057/newstats/userstats.php/1EXfBqvLTyFbL6Dr5CG1fjxNKEPSezg7yF">http://eligius.st/~wizkid057/newstats/userstats.php/1EXfBqvLTyFbL6Dr5CG1fjxNKEPSezg7yF</A>
<BR><BR>
<BR>
<CENTER><H3>Recent Blocks</H3></CENTER>

<?php
	# Display partial block list on main page
	$blocklimit = 8;
	$subcall = 1;
	include("blocks.php");
?>
<SMALL>(This table updates in near-realtime automatically in most browsers.  Share counts are converted to difficulty 1 shares.)</SMALL><BR>
<BR>
<div id="line"></div>
<div id="graphdiv3" style="width:100%; height:275px;"></div>
<script type="text/javascript">
  g2 = new Dygraph(
    document.getElementById("graphdiv3"),
    "poolhashrategraph.php",
   	{ strokeWidth: 2.25,
	'hashrate': {fillGraph: true },
	labelsDivStyles: { border: '1px solid black' },
	title: '<?php echo $poolname; ?> Hashrate Graph',
	xlabel: 'Date',
	ylabel: 'Gh/sec',
	animatedZooms: true,
	includeZero: true
	}
  );
</script>
<BR>
<div id="line"></div>
<CENTER><H3><?php echo $poolname; ?> Reward Variance</H3></CENTER>
<div id="graphdiv4" style="width:100%; height:150px;"></div>
<script type="text/javascript">
  g3 = new Dygraph(
    document.getElementById("graphdiv4"),
    "poolluckgraph.php",
   	{ strokeWidth: 2.25,
	'hashrate': {fillGraph: true },
	labelsDivStyles: { border: '1px solid black' },
	xlabel: 'Date',
	ylabel: 'Percent of PPS',
	animatedZooms: true
	}
  );
</script>

<div id="graphdiv5" style="width:100%; height:150px;"></div>
<script type="text/javascript">
  g3 = new Dygraph(
    document.getElementById("graphdiv5"),
    "poolluckgraph.php?btc=1",
   	{ strokeWidth: 2.25,
	fillGraph: true,
	labelsDivStyles: { border: '1px solid black' },
	xlabel: 'Date',
	ylabel: 'Est. BTC',
	animatedZooms: true
	}
  );
</script>
<SMALL>(These graphs shows the estimated earnings, as a percentage of maximum PPS, of a hypothetical 1GH miner who started mining at <?php echo $poolname; ?> at block height 210000.)</SMALL><BR>

<BR><div id="line"></div>
<H3><CENTER>Top Miners (3 hr rate) <A HREF="topcontributors.php">(Full)</A></CENTER></H3>

<?php 
	# Display partial contributor list on main page
	$minilimit = "limit 10"; 
	include("topcontributors.php"); 


?>
<BR><BR>
Current network difficulty: <?php echo $netdiff; ?><BR>
Current maximum PPS at this difficulty: <?php 
$xpps = sprintf("%.12f",currentPPSsatoshi($netdiff)/100000000);
$pps = substr($xpps,0,10);
$subpps = substr($xpps,10,4);
print "$pps<small>$subpps</small>";
?> BTC<BR>
Average time to find a block at <?php echo $phash; ?> at this difficulty: <?php echo prettyDuration($netdiff/($sharesperunit*20)); ?><BR>
Average pool blocks per day at <?php echo $phash; ?> at this difficulty: <?php echo printf("%.2f",86400/($netdiff/($sharesperunit*20))); ?><BR>


<?php
	print_stats_bottom(); 
?>
