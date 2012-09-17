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


require_once 'config.php';
$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

if (!isset($subcall)) {
	$titleprepend = "Block List - ";
	print_stats_top();
}

if (isset($blocklimit)) {
	$blim = "limit $blocklimit";
} else { 
	$blim = ""; 
}



#$sql = "select *,NOW()-time as age,time-roundstart as duration from wizkid057.stats_blocks left join users on user_id=users.id order by time desc;";
$sql = "select *,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from wizkid057.stats_blocks left join users on user_id=users.id order by time desc $blim;";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);


print "<TABLE CLASS=\"blocklist\">";

print "<TR CLASS=\"blocklistheader\">";
print "<TD>Age</TD>";
print "<TD>Round Start</TD>";
print "<TD colspan=\"3\">Round Duration</TD>";
print "<TD>Accepted Shares</TD>";
print "<TD colspan=\"2\">Rejected Shares</TD>";
print "<TD>Difficulty</TD>";
print "<TD>Luck</TD>";
print "<TD>Confirmations</TD>";
print "<TD>Contributor</TD>";
print "<TD>Height</TD>";
print "<TD>Block Hash</TD>";
print "</TR>";

$gc = 0;
$oc = 0;

for($ri = 0; $ri < $numrows; $ri++) {

        $row = pg_fetch_array($result, $ri);

	if (isset($row["acceptedshares"])) { $luck = 100 * ($row["network_difficulty"] / $row["acceptedshares"]); } else { $luck = 0; }
	if ($luck > 9999) { $luck = ">9999%"; } else { $luck = round($luck,2)."%"; }


	$roundstart = substr($row["roundstart"],0,19);
	if ($row["confirmations"] >= 120) { $confs = "Confirmed"; $gc++; }
	else if ($row["confirmations"] == 0) { $confs = "Stale"; $oc++; $luck = "n/a"; $roundstart = "<SMALL>(".substr($row["time"],0,19); $roundstart .= ")</SMALL>"; }
	else { $confs = $row["confirmations"]." of 120"; }

	if ($ri % 2) { $isodd = "odd"; } else { $isodd = ""; }

	if ($row["confirmations"] == 0) { print "<TR BGCOLOR=\"#FFDFDF\" class=\"$isodd"."blockorphan\">"; } 
	else if ($row["confirmations"] >= 120) { print "<TR BGCOLOR=\"#DFFFDF\" class=\"$isodd"."blockconfirmed\">"; }
	else { print "<TR class=\"$isodd\">"; }
	print "<TD>".prettyDuration($row["age"],false,1)."</TD>";



	print "<TD>".$roundstart."</TD>";
	#print "<TD>".($row["duration"]>0?prettyDurationshort($row["duration"],false,1):"")."</TD>";

	if (isset($row["duration"])) {
		list($seconds, $minutes, $hours) = extractTime($row["duration"]);
		print "<td style=\"width: 1.5em;  text-align: right;\">$hours</td><td style=\"width: 1.5em;  text-align: right;\">$minutes</td><td style=\"width: 1.5em;  text-align: right;\">$seconds</td>";
	} else {
		print "<td style=\"text-align: right;\" colspan=\"3\">n/a</td>";
	}

	print "<TD style=\"text-align: right;\">".$row["acceptedshares"]."</TD>";
	if (isset($row["rejectedshares"])) {
		$rper = "<SMALL>(".round(  (($row["rejectedshares"]/($row["rejectedshares"]+$row["acceptedshares"])) *100) ,2)."%)</SMALL>";
		print "<TD style=\"text-align: right;\">".$row["rejectedshares"]."</TD><TD style=\"text-align: right;\">".$rper."</TD>";
	} else {
		print "<TD colspan=\"2\" style=\"text-align: right;\">n/a</TD>";
	}
	print "<TD style=\"text-align: right;\">".sprintf("%.3e",round($row["network_difficulty"],4))."</TD>";
	print "<TD style=\"text-align: right;\">".$luck."</TD>";
	print "<TD style=\"text-align: right;\">".$confs."</TD>";
	if (isset($row['keyhash'])) {
		$fulladdress =  \Bitcoin::hash160ToAddress(bits2hex($row['keyhash']));
		$address = substr($fulladdress,0,10)."...";
	} else {
		 $address = "(Unknown user)"; 
	}
	#print "<TD><A HREF=\"userstats.php/".$row["username"]."\">".$row["username"]."</A></TD>";
	print "<TD><A HREF=\"userstats.php/".$fulladdress."\">".$address."</A></TD>";


	if ((isset($row["height"])) && ($row["height"] > 0)) {
		$ht = $row["height"];
	} else {
		$ht = "n/a";
	}
	print "<TD style=\"text-align: right;\">$ht</TD>";

	$nicehash = "...".substr($row["blockhash"],40,24);
	#print "<TD>".$row["blockhash"]."</TD>";
	print "<TD><A HREF=\"http://blockchain.info/block/".$row["blockhash"]."\">".$nicehash."</A></TD>";

	print "</TR>";

}

print "</TABLE>";

print "<BR>Confirmed blocks: $gc blocks --- Stale blocks: $oc blocks\n";

if (!isset($subcall)) {
	print_stats_bottom();
}


?>
