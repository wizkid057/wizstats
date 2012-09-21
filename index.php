<?php
#    wizstats - bitcoin pool web statistics
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

include("config.php");
$bodytags = "onLoad=\"initShares();\"";

print_stats_top();

?>

<SMALL>Donations to stats development: <B>1Stats</B>gBq3C8PbF1SJw487MEUHhZahyvR</SMALL>
<BR><BR>
<B>NOTE: THESE PAGES ARE A WORK IN PROGRESS.  PLEASE REPORT ANY ISSUES ON GITHUB HERE: <A HREF="https://github.com/wizkid057/wizstats/issues">wizstats github issues</A>.</B>
<BR><BR>
Use http://eligius.st/~wizkid057/newstats/userstats.php/[your miner address] for individual stats.<BR>
For example, <A HREF="http://eligius.st/~wizkid057/newstats/userstats.php/1EXfBqvLTyFbL6Dr5CG1fjxNKEPSezg7yF">http://eligius.st/~wizkid057/newstats/userstats.php/1EXfBqvLTyFbL6Dr5CG1fjxNKEPSezg7yF</A>
<BR><BR>
<A HREF="blocks.php">Full Eligius Block List</A>
<BR><BR>
<BR>
Last 8 blocks<BR>

<?php
	# Display partial block list on main page
	$blocklimit = 8;
	$subcall = 1;
	include("blocks.php");
?>
<SMALL>(This table updates in near-realtime automatically in most browsers.)</SMALL><BR>

<?php

	# Pool mostly instant hashrate

	$linkindex = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");


	$sql = "select ((select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1)-(select orig_id-coalesce(rightrejects,0) from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1)-(select coalesce(sum(rejected_shares),0) from stats_shareagg where time >= (select to_timestamp((date_part('epoch', time)::integer / 675::integer)::integer * 675::integer) from stats_blocks where server=$serverid and confirmations > 0 order by id desc limit 1))+(select count(*) from shares where server=$serverid and our_result=true and id > (select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1))) as currentround;";
	$result = pg_exec($linkindex, $sql); $row = pg_fetch_array($result, 0);
	$roundshares = $row["currentround"];

	$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
	$result = pg_exec($linkindex, $sql); $row = pg_fetch_array($result, 0);
	$hashrate1250 = $row["avghash"];

	$sql = "select (sum(accepted_shares)*pow(2,32))/10800 as avghash from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'3 hours'::interval";
	$result = pg_exec($linkindex, $sql); $row = pg_fetch_array($result, 0);
	$hashrate3hr = $row["avghash"];

	# get latest block height
	$sql = "select height from stats_blocks where server=$serverid and confirmations > 0 and height > 0 order by id desc limit 1;";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$blockheight = $row["height"];


	print "<BR>Current pool hashrate: ".prettyHashrate($hashrate1250)." (3 hour average: ".prettyHashrate($hashrate3hr).")<BR>";
	print "<div id=\"sharecounter\">Accepted shares submitted since our last block: $roundshares</div><BR>";

?>

<div id="graphdiv3" style="width:95%; height:375px;"></div>
<script type="text/javascript">
  g2 = new Dygraph(
    document.getElementById("graphdiv3"),
    "poolhashrategraph.php",
   	{ strokeWidth: 2.25,
	'hashrate': {fillGraph: true },
	labelsDivStyles: { border: '1px solid black' },
	title: 'Eligius-Ra Hashrate Graph',
	xlabel: 'Date',
	ylabel: 'GH/sec'
	}
  );
</script>

Top Miners (3 hr rate) <A HREF="topcontributors.php">(Full)</A><BR>

<?php 
	# Display partial contributor list on main page
	$minilimit = "limit 10"; 
	include("topcontributors.php"); 
?>

<?php 
	# stats footer

	$sharesperunit = ($hashrate3hr/4294967296)/20;

	$polltimer = 60000;
	$fullpolltimer = 601000;

	$afterbodyextras = "<script language=\"javascript\">
	var intCountShares = $roundshares;
	var intSharesPerUnit = $sharesperunit;
	var intCurrentBlockHeight = $blockheight;
	var latestBlockHeight = $blockheight;

	function updateSharesData()
	{

		\$.getJSON(\"instant.php/livedata.json\",
			function(data){
				intCountShares = data.roundsharecount;
				intSharesPerUnit = data.sharesperunit;
				latestBlockHeight = data.lastblockheight;
			});

		setTimeout(\"updateSharesData()\",$polltimer);
		setTimeout(\"checkNewBlock()\",500);
		//\$(\"#blocklistheaderid\").after(\"<TR><TD COLSPAN=14>a</TD></TR>\");
	}

	function checkNewBlock()
	{
		if (latestBlockHeight != intCurrentBlockHeight) {
			// new block found... add it!
			//alert('new block! ' + latestBlockHeight);
			\$.getJSON(\"instant.php/blockinfo.json?height=\"+latestBlockHeight+\"&cclass=\"+\$(\"#blocklisttable tr:last\").attr('class'),
				function(data){
					\$(\"#blocklistheaderid\").after(data.blockrow);
					\$(\"#blocklisttable tr:last\").remove();
					intCurrentBlockHeight = latestBlockHeight;
				});
		}
	}

	function countShares()
	{
		intCountShares += intSharesPerUnit;
		sharecounter.innerHTML = 'Accepted shares submitted since our last block: ' + Math.round(intCountShares) + ' (Est)';
		setTimeout(\"countShares()\",50);
	}

	function initShares()
	{
		countShares(); 
		setTimeout(\"updateSharesData()\",$polltimer);
		setTimeout(\"updateBlockTable()\",$fullpolltimer);
	}

	function updateBlockRow(relem, rblockid) {
			//alert(relem + '---' + rblockid);
			\$.getJSON(\"instant.php/blockinfo.json?dbid=\"+rblockid+\"&cclass=\"+\$(relem).attr('class'),
				function(data){
					\$(relem).after(data.blockrow);
					\$(relem).remove();
				});
	}

	function updateBlockTable()
	{
		$('#blocklisttable tr').each(function(index, elem) { 
			if (index>0) {
				if (\$(elem).attr('id').substring(0,8) == 'blockrow') {
					//alert(\$(elem).attr('id').substring(8));
					updateBlockRow(elem,\$(elem).attr('id').substring(8));
				}
			}

		});
		setTimeout(\"updateBlockTable()\",$fullpolltimer);
	}

	</script>";
	print_stats_bottom(); 
?>
