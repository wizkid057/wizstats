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

	# 12 hour hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/43200 as avghash,sum(accepted_shares) as share_total from $psqlschema.stats_shareagg where server=$serverid and user_id in $wherein and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'12 hours'::interval)::integer / 675::integer) * 675::integer)";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$u12avghash = isset($row["avghash"])?$row["avghash"]:0;
	$u12shares = isset($row["share_total"])?$row["share_total"]:0;

	# 3 hour hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/10800 as avghash,sum(accepted_shares) as share_total from $psqlschema.stats_shareagg where server=$serverid and user_id in $wherein and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'3 hours'::interval)::integer / 675::integer) * 675::integer)";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$u16avghash = isset($row["avghash"])?$row["avghash"]:0;
	$u16shares = isset($row["share_total"])?$row["share_total"]:0;

	# 22.5 minute hashrate
	$sql = "select (sum(accepted_shares)*pow(2,32))/1350 as avghash,sum(accepted_shares) as share_total from $psqlschema.stats_shareagg where server=$serverid and user_id in $wherein and time > to_timestamp((date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1))::integer / 675::integer)::integer * 675::integer)-'1350 seconds'::interval";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
	$u2avghash = isset($row["avghash"])?$row["avghash"]:0;
	$u2shares = isset($row["share_total"])?$row["share_total"]:0;

	# instant hashrates from CPPSRB
	$cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
	$cppsrbjsondec = json_decode($cppsrbjson,true);
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
