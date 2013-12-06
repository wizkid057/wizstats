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

require_once 'includes.php';

if( isLocked() ) die( "Already running.\n" ); 

$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );
$link2 = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );

### OPTIMIZE THIS TO KNOW WHEN THE LAST BLOCK WAS?!
$sql = "INSERT INTO $psqlschema.stats_blocks (server, orig_id, time, user_id, solution, network_difficulty) select server, id, time, user_id, solution, (pow(10,((29-hex_to_int(substr(encode(solution,'hex'),145,2)))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where upstream_result=true and solution NOT IN (select solution from $psqlschema.stats_blocks);";
$result = pg_exec($link, $sql);

$sql = "select * from $psqlschema.stats_blocks where (confirmations < 120 and confirmations > 0) or (blockhash IS NULL) or ((acceptedshares is null or roundstart is null) and (confirmations > 0)) or (confirmations < 120 and time > NOW()-'1 day'::interval) order by time asc";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);

for($ri = 0; $ri < $numrows; $ri++) {

	$row = pg_fetch_array($result, $ri);
	$sol = $row["solution"];

	$sol = substr($sol,2,160);
        $sol = bigtolittle($sol);
        $blockhash = pack("H*", $sol);
        $blockhash = hash("sha256",hash("sha256",$blockhash,true));
        $blockhash = revhex($blockhash);

	$getblockjson = "{\"method\":\"getblock\", \"id\":\"1\", \"params\":[\"$blockhash\"]}";
	$getblock = my_curl_request($bitcoinrpcurl, $getblockjson);

	$height = 0;
	$dome = 0;
	if (isset($getblock["error"])) {
		$e = $getblock["error"];
		if ($e["message"] == "Block not found") {
			print "ORPHAN $blockhash {$row["id"]} {$row["orig_id"]}\n";
			$confs = 0;
		}
	} else {
		$jresult = $getblock["result"];
		$height = $jresult["height"];
		$confs = $jresult["confirmations"];
		if ($confs == 0) {
			print "ORPHAN $blockhash\n";
		} else {
			print "GOOD   $blockhash ($confs confs) HEIGHT: $height\n";
		}


	}

	if ($confs >= 120) {
		$confs = 120;
	}

	print "XXX: ".$row["roundstart"]."-".$row["acceptedshares"]."\n";

	$orig_id = $row["orig_id"];
	$server = $row["server"];
	$id = $row["id"];
	$btime = $row["time"];
	if (( $confs > 0 && (($row["roundstart"] == "") || ($row["acceptedshares"] == "")) ) || ($dome == 1)) {

		print "Missing data for $blockhash\n";

		# fix 090712 - was counting back to orphans...
		$sql = "select * from $psqlschema.stats_blocks where orig_id < $orig_id and confirmations > 0 and server=$server order by time desc limit 1";
		print "SQL1: $sql\n";

		$result2 = pg_exec($link2, $sql);
		$numrows2 = pg_numrows($result2);
		if ($numrows2 < 1) {
			# first block ever? :-\
			# must be an initial aggregation... ?
			$orig_id2 = 0; ###? ???????
			$roundstart = $row["time"];
			$lastrightrejects = 0;
		} else {
			$row2 = pg_fetch_array($result2, 0);
			$orig_id2 = $row2["orig_id"];
			$roundstart = $row2["time"];
			if (isset($row2["rightrejects"])) {
				$lastrightrejects = $row2["rightrejects"];
			} else {
				# obviously this can happen now...
				$btime2 = $row2["time"];
				$id2 = $row2["id"];

				## UPDATE RIGHT REJECTS
				$sql = "select case when (least(to_timestamp(((date_part('epoch', '$btime2'::timestamp without time zone)::integer + 675::integer) / 675::integer) * 675::integer),((select time from $psqlschema.stats_blocks where id>$orig_id2 order by id asc limit 1))) < NOW()) THEN COUNT(*) ELSE NULL END as rightrejects from public.shares where server=$server and our_result!=true and id > $orig_id2 and time >= '$btime2' and time < least(to_timestamp(((date_part('epoch', '$btime2'::timestamp without time zone)::integer + 675::integer) / 675::integer) * 675::integer),((select time from $psqlschema.stats_blocks where orig_id>$orig_id2 and confirmations > 0 order by id asc limit 1)));";
				print "SQL2: $sql ; \n";
				$result2 = pg_exec($link2, $sql);
				$row2 = pg_fetch_array($result2, 0);
				$rightrejects = $row2["rightrejects"];

				print "RIGHT REJECTS (prev): $rightrejects\n";

				if ($rightrejects > 0) {
					$sql = "update $psqlschema.stats_blocks set rightrejects=$rightrejects where id=$id2";
					$result2 = pg_exec($link2, $sql);
					$lastrightrejects = $rightrejects;
				} else {
					$lastrightrejects = 0; # !!!!!!
				}
			}
		}
		# count block shares exactly using vardiff for POT targetmask...
		$sql = "select sum(pow(2,targetmask-32)) as blockshares from shares where server=$serverid and our_result=true and id > $orig_id2 and id <= $orig_id;";
		print "SQL C: $sql\n";
		$result2 = pg_exec($link2, $sql);
		$row2 = pg_fetch_array($result2, 0); # does NOT include rejects....
		$sharecount = $row2["blockshares"];

		## UPDATE LEFT REJECTS
		$sql = "select count(*) as leftrejects from public.shares where server=$server and our_result!=true and id > $orig_id2 and id < $orig_id and time <= '$btime' and time > greatest(to_timestamp((date_part('epoch', '$btime'::timestamp without time zone)::integer / 675::integer) * 675::integer),(select time from $psqlschema.stats_blocks where orig_id=$orig_id2 order by id desc limit 1));";
		print "SQL2: $sql ; \n";
		$result2 = pg_exec($link2, $sql);
		$row2 = pg_fetch_array($result2, 0);
		$leftrejects = $row2["leftrejects"];

		print "LEFT REJECTS: $leftrejects\n";

		## UPDATE FAR LEFT REJECTS
		$sql = "select SUM(rejected_shares) as farleftrejects from $psqlschema.stats_shareagg where server=$server and time < to_timestamp((date_part('epoch', '$btime'::timestamp without time zone)::integer / 675::integer) * 675::integer) and time > (select time from $psqlschema.stats_blocks where orig_id=$orig_id2 order by id desc limit 1);";
		print "SQL2: $sql ; \n";
		$result2 = pg_exec($link2, $sql);
		$row2 = pg_fetch_array($result2, 0);
		$farleftrejects = $row2["farleftrejects"];

		print "FAR LEFT REJECTS: $farleftrejects\n";


		$prshares = $leftrejects + $farleftrejects + $lastrightrejects;
		print "Rejected agg count: $prshares\n";

		$rejectcount = $prshares;

		print "--- Total Shares: ".($sharecount + $rejectcount)." minus $rejectcount Rejects = $sharecount\n";
		$accepted = $sharecount;

		$addupdate = ", roundstart='$roundstart', acceptedshares=$accepted, rejectedshares=$rejectcount ";
		$sql = "update $psqlschema.stats_blocks set roundstart='$roundstart', acceptedshares=$accepted, rejectedshares=$rejectcount,leftrejects=$leftrejects where id=$id";
		$result2 = pg_exec($link2, $sql);
	}

	if (($row["blockhash"] != $blockhash) || ($row["confirmations"] != $confs)) {
		if (strlen($height) > 0) {
			$sql = "update $psqlschema.stats_blocks set height=$height, blockhash='$blockhash', confirmations=$confs where id=$id";
			$result2 = pg_exec($link2, $sql);
		}
		else {
			print "Broken height $height\n";
		}
	}

	if ((!isset($row["rightrejects"])) && ($confs > 0)  ) {
		## UPDATE RIGHT REJECTS
		$sql = "select case when (least(to_timestamp(((date_part('epoch', '$btime'::timestamp without time zone)::integer + 675::integer) / 675::integer) * 675::integer),((select time from $psqlschema.stats_blocks where id>$orig_id order by id asc limit 1))) < NOW()) THEN COUNT(*) ELSE NULL END as rightrejects from public.shares where server=$server and our_result!=true and id > $orig_id and time >= '$btime' and time < least(to_timestamp(((date_part('epoch', '$btime'::timestamp without time zone)::integer + 675::integer) / 675::integer) * 675::integer),((select time from $psqlschema.stats_blocks where orig_id>$orig_id and confirmations > 0 order by id asc limit 1)));";
		print "SQL2: $sql ; \n";
		$result2 = pg_exec($link2, $sql);
		$row2 = pg_fetch_array($result2, 0);
		$rightrejects = $row2["rightrejects"];

		print "RIGHT REJECTS: $rightrejects\n";

		if ($rightrejects > 0) {
			$sql = "update $psqlschema.stats_blocks set rightrejects=$rightrejects where id=$id";
			$result2 = pg_exec($link2, $sql);
		} 

	}

}

# Clean up potentially duplicate blocks, keeping the oldest
$sql = "delete from $psqlschema.stats_blocks using $psqlschema.stats_blocks sb2 where $psqlschema.stats_blocks.blockhash = sb2.blockhash AND $psqlschema.stats_blocks.id < sb2.id;";
$result = pg_exec($link, $sql);

unlink( LOCK_FILE ); 

?>
