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

error_reporting(E_ALL ^ E_NOTICE);

require_once 'includes.php';
$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");


$sql = "select sum(hashrate) as hr,time from $psqlschema.stats_shareagg where server=$serverid and time > NOW()-'10 days'::interval-'12 hours'::interval group by time order by time asc;";
header("Content-type: text/csv");
$query_hash = hash("sha256", $sql);
$cacheddata = get_stats_cache($link, 4, $query_hash);
if ($cacheddata != "") {
	print $cacheddata;
	exit;
}


$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);


$tdata = "date,hashrate,hashrate3hr,hashrate12hr\n";
print $tdata;


	$an = 0;
	$an2 = 0;

	for($i=0;$i<64;$i++) { $ta[$i] = 0; $ta2[$i] = 0; }
	for($i=0;$i<128;$i++) { $lagavg3[$i] = 0; $lagavg12[$i] = 0; }

	$lav = 0;

	for($ri = 0; $ri < $numrows+32; $ri++) {
		if ($ri < $numrows) {
			$row = pg_fetch_array($result, $ri);
			$ta[$an] = $row["hr"]; $an++; if ($an == 16) { $an = 0; } $av = 0; for($i=0;$i<16;$i++) { $av += $ta[$i]; } if ($ri > 16) {       $av = $av / 16; } else { $av = ""; }
			$ta2[$an2] = $row["hr"]; $an2++; if ($an2 == 64) { $an2 = 0; } $av2 = 0; for($i=0;$i<64;$i++) { $av2 += $ta2[$i]; } if ($ri > 64) {       $av2 = $av2 / 64; } else { $av2 = ""; }

			$lagavg3[$lav] = round($av/1000000000,2);
			$lagavg12[$lav] = round($av2/1000000000,2);
		} else {
			unset($row);
			$lagavg3[$lav] = "";
			$lagavg12[$lav] = "";
		}

		if ($ri > 64) {

			$lagavg675[$lav] = $row["time"].",".$row["hr"]/1000000000;

			if ($ri > 64+32) {
				$lavx = $lav - 32; if ($lavx < 0) { $lavx+=128; }
				$lavy = $lav - 24; if ($lavy < 0) { $lavy+=128; }
				$lavz = $lav - 0; if ($lavz < 0) { $lavz+=128; }
				$tline = $lagavg675[$lavx].",".$lagavg3[$lavy].",".$lagavg12[$lavz]."\n";
				$tdata .= $tline;
				print $tline;
			}
		}

		$lav++; if ($lav == 128) { $lav = 0; }

	}

	set_stats_cache($link, 4, $query_hash, $tdata, 675);

exit();


?>

