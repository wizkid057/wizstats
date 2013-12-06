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


require_once 'includes.php';
require_once 'blocks_functions.php';

$blocks_show_stale = 0;

if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

if (!isset($subcall)) {
	$titleprepend = "Block List - ";
	print_stats_top();
	if (isset($_GET["show_stale"])) {
		if ($_GET["show_stale"] == 1) {
			$blocks_show_stale = 1;
		}
	}

	if (!$blocks_show_stale) {
		print "<SMALL><A HREF=\"?show_stale=1\">(Show stale/orphan blocks in list)</A></SMALL><BR>";
	} else {
		print "<SMALL><A HREF=\"?show_stale=0\">(Hide stale/orphan blocks in list)</A></SMALL><BR>";
	}


} else {
	$blocks_show_stale = 0;
}

if (isset($blocklimit)) {
	$blim = "limit $blocklimit";
} else {
	$blim = "";
	print "<SMALL>Click on a header item to sort the list</SMALL><BR><BR>";
}


if ($blocks_show_stale) {
	$sql = "select *,stats_blocks.id as blockid,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from $psqlschema.stats_blocks left join users on user_id=users.id where server=$serverid order by time desc $blim;";
} else {
	$sql = "select *,stats_blocks.id as blockid,date_part('epoch', NOW())::integer-date_part('epoch', time)::integer as age,date_part('epoch', time)::integer-date_part('epoch', roundstart)::integer as duration from $psqlschema.stats_blocks left join users on user_id=users.id where confirmations > 0 and server=$serverid order by time desc $blim;";
}
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);


if (isset($blocklimit)) {
	print block_table_start(0);
} else {
	print block_table_start(1);
}
print block_table_header();


$gc = 0;
$oc = 0;

for($ri = 0; $ri < $numrows; $ri++) {

        $row = pg_fetch_array($result, $ri);
	if ($row["confirmations"] >= 120) { $gc++; }
	if ($row["confirmations"] == 0) { $oc++; }
	if ($ri % 2) { $isodd = "odd"; } else { $isodd = ""; }
	if (($row["confirmations"] > 0) || (($row["confirmations"] == 0) && ($blocks_show_stale))) {
		print block_table_row($row,$isodd);
	}

}

print block_table_end();

if (!isset($subcall)) {
	print "<BR>Confirmed blocks: $gc blocks --- Stale blocks: $oc blocks\n";
	print_stats_bottom();
}


?>
