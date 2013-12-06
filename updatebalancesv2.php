#!/usr/bin/php
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


### Updates the stats_balances table
### NOTE: This script explodes the balances.json file into memory
###       it shouldn't be a problem as long as that file remains reasonably sized


require_once 'includes.php';

if( isLocked() ) die( "Already running.\n" );

$link = pg_connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );
$link2 = pg_connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );

$serverid = 7;

# TODO: fix mostly hardcoded path
$bal = file_get_contents("/var/lib/eligius/$serverid/balances.json");
$balj = json_decode($bal,true);


$sql = "select to_timestamp((date_part('epoch', NOW())::integer / 675) * 675) as ttime;";
$result = pg_exec($link, $sql);
$row = pg_fetch_array($result, 0);
$nowtimex = $row["ttime"];

list($nowtime) = explode("+", $nowtimex);

print "Nowtime: $nowtime\n";

$insertvalues = "";
$paidinsertvalues = "";

$ucount = 0;

foreach($balj as $key => $val) {

	$mbal = (isset($val{'balance'})?$val{'balance'}:0);
	$mep = (isset($val{'everpaid'})?$val{'everpaid'}:0);
	$mec = (isset($val{'credit'})?$val{'credit'}:0);
	$mbal++; $mbal--;
	$mep++; $mep--;
	$mec++; $mec--;

	if ($key != "") {
		# convert address to bits to find the user...
		$bits =  hex2bits(\Bitcoin::addressToHash160($key));

		if ($bits != "") {
			$lastdata[$bits][0] = $mbal;
			$lastdata[$bits][1] = $mep;
			$lastdata[$bits][2] = $mec;
		}
	}
}


$sql = "select distinct on (user_id) user_id,time,username,keyhash,everpaid,credit,balance from $psqlschema.stats_balances left join users on user_id=users.id where server=$serverid and time > NOW()-'1 week'::interval order by user_id, time desc;";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);
for($ri = 0; $ri < $numrows; $ri++) {

	$row = pg_fetch_array($result, $ri);
	$user_id = $row["user_id"];

	if (!$user_id) {
		print "Error: Invalid user_id $user_id ".($row["keyhash"])." - ".($row["username"])."!\n";
		print "--- This should never happen!\n";
	} else {

		if (isset($lastdata[$row["keyhash"]])) {
			$ucount++;
			if (($lastdata[$row["keyhash"]][0] == $row["balance"]) && ($lastdata[$row["keyhash"]][1] == $row["everpaid"]) && ($lastdata[$row["keyhash"]][2] == $row["credit"])) {
				print "Duplicate Data weeded for ".$row["keyhash"]."\n";
			} else {
				if ($row["time"] == $nowtime) {
					print "Duplicate timestamp $nowtime for ".$row["keyhash"]."\n";
				} else {
					print "New Data for ".$row["keyhash"]." - ".$row["user_id"]."\n";
					$insertdata = "($serverid, '$nowtimex', $user_id, ".$lastdata[$row["keyhash"]][1].", ".$lastdata[$row["keyhash"]][0].", ".$lastdata[$row["keyhash"]][2]."), ";
					$insertvalues .= $insertdata;
				}
			}
			unset($lastdata[$row["keyhash"]]);
		} else {
			$fulladdress =  \Bitcoin::hash160ToAddress(bits2hex($row['keyhash']));
			print "This user appears to have been fully paid (removed from balances.json): $fulladdress / $user_id\n";
			if ($row["balance"] > 0) {
				$tep = $row["everpaid"] + $row["balance"];
				$insertdata = "($serverid, '$nowtimex', $user_id, $tep, 0, 0), ";
				$paidinsertvalues .= $insertdata;
			}
		}
	}
}

# failsafe for incase balances.json is corrupt or something...
# make sure we had other addresses to update before marking people as paid off...
if ($ucount > 0) {
	$insertvalues .= $paidinsertvalues;
}


foreach($lastdata as $key => $val) {
	print "Totally new miner!?!?: $key\n";
	# This is the only spot where keyhash is used directly... keep it in line with get_user_id_from_address
	$sql = "select id from public.users where keyhash='$key' order by id asc limit 1;";
	$result = pg_exec($link, $sql);
	$row = pg_fetch_array($result, 0);
	$user_id = $row["id"];
	if (!$user_id) {
		print "--- Error: New miner with $key in balances.json not found in public.users! (Probably database update lag, will catch next time)\n";
	} else {
		$insertdata = "($serverid, '$nowtimex', $user_id, ".$lastdata[$key][1].", ".$lastdata[$key][0].", ".$lastdata[$key][2]."), ";
		$insertvalues .= $insertdata;
	}
}


$insertvalues = substr($insertvalues, 0, strlen($insertvalues)-2);

if (strlen($insertvalues) > 0) {
	$sql = "insert into $psqlschema.stats_balances (server, time, user_id, everpaid, balance, credit) VALUES ". $insertvalues . "\n";
	print "Final SQL: $sql\n";
	$result = pg_exec($link, $sql);
} else {
	print "Nothing to do.\n";
}

unlink( LOCK_FILE );

?>
