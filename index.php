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

require_once "includes.php";
#$bodytags = "onLoad=\"initShares();\"";
$ldmain = "-main";

print_stats_top();

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
<SMALL>(This table updates in near-realtime automatically in most browsers.)</SMALL><BR>
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
	animatedZooms: true
	}
  );
</script>

<BR><div id="line"></div>
<H3><CENTER>Top Miners (3 hr rate) <A HREF="topcontributors.php">(Full)</A></CENTER></H3>

<?php 
	# Display partial contributor list on main page
	$minilimit = "limit 10"; 
	include("topcontributors.php"); 

	print_stats_bottom(); 
?>
