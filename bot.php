<?php



require_once 'includes.php';


if (isset($_GET["lastblockirc"])) {
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	$sql = "select username,shares.time,height from shares left join users on user_id=users.id left join stats_blocks on shares.id=stats_blocks.orig_id where shares.server=7 and upstream_result=true and confirmations > 0 order by shares.id desc limit 1;";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	$row = pg_fetch_array($result, 0);
	$username = $row["username"];
	print "ws001: $username\n\n";
	exit;
}

if (isset($_GET["lastblockdatairc"])) {
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	$sql = "select users.id as user_id,username,blockhash,shares.time,height,acceptedshares,network_difficulty,date_part('epoch', shares.time)::integer-date_part('epoch', roundstart)::integer as duration from shares left join users on user_id=users.id left join stats_blocks on shares.id=stats_blocks.orig_id where shares.server=7 and stats_blocks.server=7 and upstream_result=true and confirmations > 0 order by shares.id desc limit 1;";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	$row = pg_fetch_array($result, 0);
	$username = $row["username"];
	$height = $row["height"];
	$blockhash = $row["blockhash"];
	$acceptedshares = $row["acceptedshares"];
	$networkdifficulty = $row["network_difficulty"];
	$duration = $row["duration"];
	$user_id = $row["user_id"];

	$hashrate = ($row["acceptedshares"] * 4294967296) / $row["duration"];
	$hashratenum = $hashrate;
	$hashrate = prettyHashrate($hashrate);

	list($username,$workername) = explode("_", $username, 2);

	$nickname = get_nickname($link,get_user_id_from_address($link,$username));

	if (strlen($workername) > 0) {
		$nickname .= " Worker: $workername";
	}

	print "ws002: $blockhash $height $username $acceptedshares $networkdifficulty $duration $hashrate $nickname \n\n";
	exit;
}

if (isset($_GET["poolhashrate"])) {
	if($cppsrbjsondec = apc_fetch('cppsrb_json')) {
	} else {
		$cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
		$cppsrbjsondec = json_decode($cppsrbjson, true);
		apc_store('cppsrb_json', $cppsrbjsondec, 60);
	}
	$globalcppsrb = $cppsrbjsondec[''];
	$my_shares = $globalcppsrb["shares"];
	if (!isset($_GET["numeric"])) {
		print "Insta-hashrate for $poolname - ";
		print "64 second: " . prettyHashrate(($my_shares[64] * 4294967296)/64) . " - ";
		print "128 second: " . prettyHashrate(($my_shares[128] * 4294967296)/128) . " - ";
		print "256 second: " . prettyHashrate(($my_shares[256] * 4294967296)/256);
	} else {
		print sprintf("%F",(($my_shares[256] * 4294967296)/256));
	}
	exit;
}

# TODO: Allow worker sub-names
if (!isset($_SERVER['PATH_INFO'])) {
        print "Error: Specify username in path\n";
        exit;
}


$givenuser = substr($_SERVER['PATH_INFO'],1,strlen($_SERVER['PATH_INFO'])-1);
$bits =  hex2bits(\Bitcoin::addressToHash160($givenuser));

$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
$user_id = get_user_id_from_address($link, $givenuser);
if (!$user_id) {
        print "Error: Username $givenuser not found in database.  Please try again later.";
        exit;
}


if($cppsrbjsondec = apc_fetch('cppsrb_json')) {
} else {
	$cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
	$cppsrbjsondec = json_decode($cppsrbjson, true);
	apc_store('cppsrb_json', $cppsrbjsondec, 60);
}
$mycppsrb = $cppsrbjsondec[$givenuser];

$globalccpsrb = $cppsrbjsondec[""];

$latest_chunk = $globalccpsrb['share_log_top_chunk'];

#var_export ($globalccpsrb['share_log_top_chunk']);

#print "<PRE>";

#var_dump($mycppsrb);

$my_shares = $mycppsrb["shares"];
$my_share_log = $mycppsrb["share_log"];

#var_dump($my_shares);
#var_dump($my_share_log);

print "Insta-hashrate for $givenuser - ";
print "64 second: " . prettyHashrate(($my_shares[64] * 4294967296)/64) . " - ";
print "128 second: " . prettyHashrate(($my_shares[128] * 4294967296)/128) . " - ";
print "256 second: " . prettyHashrate(($my_shares[256] * 4294967296)/256);

