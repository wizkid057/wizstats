<?php

require_once 'includes.php';

require_once 'hashrate.php';

if (!isset($_SERVER['PATH_INFO']))
{
	print json_encode(array("error" => "No username specified in URL path  Please try again."));
	exit;
}

$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

if (pg_connection_status($link) != PGSQL_CONNECTION_OK)
{
	pg_close($link);

	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

	if (pg_connection_status($link) != PGSQL_CONNECTION_OK)
	{
		print json_encode(array("error" => "Unable to establish a connection to the stats database.  Please try again later. If this issue persists, please report it to the pool operator."));
		exit;
	}
}

$givenuser = substr($_SERVER['PATH_INFO'],1,strlen($_SERVER['PATH_INFO'])-1);
$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

$sql = "select id from public.users where keyhash='$bits' order by id asc limit 1";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);

if (!$numrows)
{
	print json_encode(array("error" => "Username $givenuser not found in database.  Please try again later. If this issue persists, please report it to the pool operator."));
	exit;
}

$row = pg_fetch_array($result, 0);
$user_id = $row["id"];

$cache_key = hash("sha256", "hashrate-json.php hashrate JSON for $givenuser with id $user_id");

$json = get_stats_cache($link, 11, $cache_key);

if ($json == "")
{
	$hashrate_info = get_hashrate_stats($link, $givenuser, $user_id);

	$json = json_encode($hashrate_info);

	set_stats_cache($link, 11, $cache_key, $json, 30);
}

print $json;

?>
