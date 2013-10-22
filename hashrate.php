<?php

require_once 'includes.php';

function add_interval_stats(&$set, $interval, $interval_name, $hashrate, $shares)
{
	$set[$interval] = array("interval" => $interval, "interval_name" => $interval_name, "hashrate" => $hashrate, "shares" => $shares);

	if (!array_key_exists("intervals", $set))
		$set["intervals"] = array();

	$intervals = &$set["intervals"];
	$intervals[] = $interval;
}

function get_hashrate_stats(&$link, $givenuser, $user_id)
{
	global $psqlschema, $serverid;

	$worker_data = get_worker_data_from_user_id($link, $user_id);
	$wherein = get_wherein_list_from_worker_data($worker_data);

	$u12avghash = 0;
	$u12shares = 0;
	$u16avghash = 0;
	$u16shares = 0;
	$u2avghash = 0;
	$u2shares = 0;

	# get current latest aggregated data time
	$sql = "select ((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'12 hours'::interval)::integer / 675::integer)*675::integer) as oldest_time";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$oldest_time = $row["oldest_time"];

	# get list of data points for last 12 hours
	$sql = "select accepted_shares,date_part('epoch', time) as ctime from $psqlschema.stats_shareagg where server=$serverid and user_id in $wherein and time > to_timestamp($oldest_time) order by time desc";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	if ($numrows) {
		$t3 = $oldest_time + (9*3600);
		$t225 = $oldest_time + (12*3600) - (22.5*60);
		for($i=0;$i<$numrows;$i++) {
			$row = pg_fetch_array($result, $i);
			$as = $row["accepted_shares"];
			$t = $row["ctime"];
			$u12shares+=$as;
			if ($t > $t3) {
				$u16shares+=$as;
				if ($t > $t225) {
					$u2shares+=$as;
				}
			}
		}
		$u12avghash = ($u12shares/(12*3600))*4294967296;
		$u16avghash = ($u16shares/(3*3600))*4294967296;
		$u2avghash = ($u2shares/(22.5*60))*4294967296;
	}

	# instant hashrates from CPPSRB
	if($cppsrbjsondec = apc_fetch('cppsrb_json')) {
	} else {
	        $cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
	        $cppsrbjsondec = json_decode($cppsrbjson, true);
	        apc_store('cppsrb_json', $cppsrbjsondec, 60);
	}
	if (isset($cppsrbjsondec[$givenuser])) {
		$mycppsrb = $cppsrbjsondec[$givenuser];
		$my_shares = $mycppsrb["shares"];
	}

	$globalccpsrb = $cppsrbjsondec[""];
	$cppsrbloaded = 1;

	# build up return value, an array of maps containing structured information about the hash rate over each interval
	$return_value = array();

	add_interval_stats($return_value, 43200, "12 hours", floatval($u12avghash), intval($u12shares));
	add_interval_stats($return_value, 10800, "3 hours", floatval($u16avghash), intval($u16shares));
	add_interval_stats($return_value, 1350, "22.5 minutes", floatval($u2avghash), intval($u2shares));

	for ($i = 256; $i > 127; $i = $i / 2) {
		if (isset($my_shares)) {
			add_interval_stats($return_value, $i, "$i seconds", ($my_shares[$i] * 4294967296) / $i, round($my_shares[$i]));
		} else {
			add_interval_stats($return_value, $i, "$i seconds", 0, 0);
		}
	}

	return $return_value;
}

?>
