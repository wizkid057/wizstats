#!/usr/bin/php
<?php
#    wizstats - bitcoin pool web statistics - 1StatsQytc7UEZ9sHJ9BGX2csmkj8XZr2
#    Copyright (C) 2013  Jason Hughes <wizkid057@gmail.com>
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

$doanyway = 0;
if (isset($argv[1])) { $doanyway = 1; }
$blockjsondec = json_decode(file_get_contents("/var/lib/eligius/$serverid/blocks/latest.json"),true);
$lastblock = substr(readlink("/var/lib/eligius/$serverid/blocks/latest.json"),0,-5);

$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );

$done = 0;
while(!$done) {
	if (strpos($lastblock,'_send') != false) {
		$coinbase = 0;
	} else {
		$coinbase = 1;
	}

	print $lastblock." $coinbase\n";


	if ($coinbase == 1) {
		# need hash of the coinbase txn from the block
		$bdata = getblock_rpc($lastblock);
		if (!isset($bdata["result"])) {
			print "Error getting block $lastblock!\n";
			exit;
		}
		$bdata = $bdata["result"];
		$txn = $bdata["tx"][0];
	} else {
		$txn = substr($lastblock,0,64);
	}
	print "--- Txn: $txn\n";

	# check if we have data for this transaction
	$sql = "select id as exists from $psqlschema.stats_transactions where hash='$txn' limit 1;";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	$createsql = 1;
	if ($numrows) {
		$row = pg_fetch_array($result, 0);
		if ($row["exists"] > 0) {
			print "Already have data for $lastblock ... assuming older are done then\n";
			if (!$doanyway) {
				exit;
			}
			$createsql = 0;
			$txid = $row["exists"];
		}
		else {
			print "No data yet\n";
		}
	} else {
		print "No data yet\n";
	}


	$txdata = getrawtransaction_rpc($txn);
	if (!isset($txdata["result"])) {
		print "Error, no data on txn $txn\n";
		exit;
	}
	$txdata = $txdata["result"];

	$outputcount = count($txdata["vout"]);

	$ctime = $txdata["time"];

	# turns out unconfirmed transactions dont have a timestamp...
	if ($ctime < 1) { $ctime = time(); }

	if (!$createsql) {
		$sql = "DELETE FROM $psqlschema.stats_payouts where transaction_id=$txid;";
		pg_exec($link, $sql);
		$sql = "DELETE FROM $psqlschema.stats_transactions where id=$txid;";
		pg_exec($link, $sql);
		$createsql = 1;
	}

	if ($createsql) {
		# add txn to sql...
		$sql = "insert into $psqlschema.stats_transactions (time, hash, block_id, coinbase) VALUES (to_timestamp($ctime), '$txn', ";
		if ($coinbase) {
			$sqlx = "select id from $psqlschema.stats_blocks where blockhash='$lastblock' limit 1";
			$resultx = pg_exec($link, $sqlx);
			$rowx = pg_fetch_array($resultx, 0);
			if (!($rowx["id"] > 0)) {
				print "ERROR... stats do not know about block $lastblock... ?\n";
				exit;
			}
			$block_id = $rowx["id"];
			$sql .= "$block_id, true)";
		} else {
			$sql .= "NULL, false)";
		}
		$sql .= " RETURNING id;";
		print "SQL: $sql\n";
		$result = pg_exec($link, $sql);
		$row = pg_fetch_array($result, 0);
		if (!($row["id"] > 0)) {
			print "ERROR!\n";
			exit;
		}
		$txid = $row["id"];
	}

	$sql = "DELETE FROM $psqlschema.stats_payouts where transaction_id=$txid;";
	pg_exec($link, $sql);

	$sql = "INSERT INTO $psqlschema.stats_payouts (user_id, address, is_pool_address, transaction_id, amount) VALUES ";

	for($i=0;$i<$outputcount;$i++) {
	        $out = $txdata["vout"][$i];
		$ammt = $out["value"]*100000000;
		if (!isset($out["scriptPubKey"]["addresses"][0])) {
			print "Script cant handle nonstandard output $i of this txn yet\n";
			exit;
		}
		$addr = $out["scriptPubKey"]["addresses"][0];
		$user_id = get_user_id_from_address($link,$addr);
		$ispool = "false";
		if ($user_id == 0) { $user_id = "NULL"; $ispool = "true"; }
		$sql .= "($user_id, '$addr', $ispool, $txid, $ammt), ";
	}
	$sql = substr($sql,0,-2);
	print "SQL: $sql\n\n\n";
	pg_exec($link, $sql);





	print "\n";
	$lastblock = $blockjsondec[""]["mylastblk"];
	if (strlen($lastblock) < 64) { print "Error. No last block!?\n"; exit; }
	$blockjsondec = json_decode(file_get_contents("/var/lib/eligius/$serverid/blocks/".($lastblock).".json"),true);
}


?>
