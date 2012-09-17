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

print_stats_top();

?>
<SMALL>Donations to stats development: <B>1Stats</B>gBq3C8PbF1SJw487MEUHhZahyvR</SMALL>
<BR><BR>
<B>NOTE: THESE PAGES ARE A WORK IN PROGRESS.  PLEASE REPORT ANY ISSUES TO <I>wizkid057 at gmail.com</I>.</B>
<BR>NOTE: 09/15/12: Greetings, Eligius Miners!  I've not forgotten about these stats!  Theres still a little bit of backend/bugfix stuff to be done, but, I knocked out most of the major stuff this afternoon.  You will notice that your hashrates are now correctly reported and that the pool hashrate and such is also correct and reported.  We also have a current round share counter now.  Lots more to come! :)<BR><BR>
<BR><BR>
Use http://eligius.st/~wizkid057/newstats/userstats.php/[your miner address] for individual stats.<BR>
For example, <A HREF="http://eligius.st/~wizkid057/newstats/userstats.php/1EXfBqvLTyFbL6Dr5CG1fjxNKEPSezg7yF">http://eligius.st/~wizkid057/newstats/userstats.php/1EXfBqvLTyFbL6Dr5CG1fjxNKEPSezg7yF</A>
<BR><BR>
<A HREF="blocks.php">Full Eligius Block List</A>
<BR><BR>
<BR>
Last 8 blocks<BR>
<?php 
$blocklimit = 8;
$subcall = 1;
include("blocks.php"); 
?>


<?php
$linkindex = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");


$sql = "select ((select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1)-(select orig_id-rightrejects from stats_blocks where server=$serverid order by id desc limit 1)-(select sum(rejected_shares) from stats_shareagg where time >= (select to_timestamp((date_part('epoch', time)::integer / 675::integer)::integer * 675::integer) from stats_blocks where server=$serverid order by id desc limit 1))+(select count(*) from shares where server=$serverid and our_result=true and id > (select id from shares where server=$serverid and time < (select time from stats_shareagg where server=$serverid order by id desc limit 1) order by id desc limit 1))) as currentround;";
$result = pg_exec($linkindex, $sql); $row = pg_fetch_array($result, 0);
$roundshares = $row["currentround"];

$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
$result = pg_exec($linkindex, $sql); $row = pg_fetch_array($result, 0);
$hashrate1250 = $row["avghash"];

$sql = "select (sum(accepted_shares)*pow(2,32))/10800 as avghash from $psqlschema.stats_shareagg where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'3 hours'::interval";
$result = pg_exec($linkindex, $sql); $row = pg_fetch_array($result, 0);
$hashrate3hr = $row["avghash"];

print "<BR>Current pool hashrate: ".prettyHashrate($hashrate1250)." (3 hour average: ".prettyHashrate($hashrate3hr).")<BR>";
print "Accepted shares submitted since our last block: $roundshares<BR>";
?>

<div id="graphdiv3"
  style="width:95%; height:375px;"></div>
<script type="text/javascript">
  g2 = new Dygraph(
    document.getElementById("graphdiv3"),
    "poolhashrategraph.php", // path to CSV file
   	{

		strokeWidth: 2.25,

		'hashrate': {fillGraph: true },
      labelsDivStyles: { border: '1px solid black' },
      title: 'Eligius-Ra Hashrate Graph',
      xlabel: 'Date',
      ylabel: 'GH/sec'          // options
	}
  );
</script>

Top Miners (3 hr rate) <A HREF="topcontributors.php">(Full)</A><BR>
<?php $minilimit = "limit 10"; include("topcontributors.php"); ?>

<!--<H3>MUCH MORE TO COME - PLEASE BE PATIENT</H3>

<BR><BR>
I'm working as quickly as I can to get these stats much more useful and presentable, but it is a time consuming process.<BR>
Please, any donations will help further development and would be greatly appreciated: <B>17RGKaQHSC882xpB84MEA7i52zbwh9AFkR</B><BR><BR>
Thanks for using the new stats!<bR>
<I>-wizkid057</I>-->



<?php print_stats_bottom(); ?>
