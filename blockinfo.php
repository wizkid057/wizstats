<?php

require_once 'includes.php';

if (!isset($_SERVER['PATH_INFO'])) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>Error:</B> No blockhash specified in URL path  Please try again.</FONT><BR>";
	print_stats_bottom();
	exit;
}


print_stats_top();

$blockhash = substr($_SERVER["PATH_INFO"],1,64);

if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

$getblockjson = "{\"method\":\"getblock\", \"id\":\"1\", \"params\":[\"$blockhash\"]}";

$query_hash = "blockinfo.php " . hash("sha256", "blockinfo.php $getblockjson");
$getblock = apc_fetch($query_hash);

if (!isset($getblock["result"])) {
	# check if Eligius block
	$getblock = my_curl_request($bitcoinrpcurl, $getblockjson);
	if (!isset($getblock["result"])) {
		$getblock["result"] = "Error";
		apc_store($query_hash, $getblock, 300);
	} else {
		$blockhashesc = $blockhash;
		$sql = pg_prepare($link, "blockinfo", "select blockhash from $psqlschema.stats_blocks where server=$serverid and blockhash=$1 limit 1");
		$result = pg_execute($link, "blockinfo", array($blockhashesc));
		$numrows = pg_numrows($result);
		if ($numrows == 1) {
			apc_store($query_hash, $getblock, 864000);
		} else {
			# not Eligius block...
			$getblock["result"] = "NonEligius";
			apc_store($query_hash, $getblock, 300);
		}
	}
}
if (($getblock["result"]["hash"] != $blockhash) || (strlen($blockhash) != 64)) {
	if ((isset($getblock["result"])) && ($getblock["result"] == "NonEligius")) {
		print "Block is not from $poolname - $blockhash<BR><a HREF=\"http://blockchain.info/block/$blockhash\">View $blockhash on Blockchain.info</A>\n";
	} else {
		print "Block not found - $blockhash\n";
	}
	print_stats_bottom();
	exit;
}

$block = $getblock["result"];

print "<H1>Block #{$block["height"]}</H2>";

$txcount = count($block["tx"]);

print "<TABLE BORDER=1>";

print "<TR><TD><B>Block hash:</B></TD><TD>$blockhash<BR><A HREF=\"http://blockchain.info/block/$blockhash\" TARGET=\"_blank\">View on Blockchain.info</A> - <A HREF=\"http://blockexplorer.com/block/$blockhash\" TARGET=\"_blank\">View on Blockexplorer.com</A></TD></TR>";
print "<TR><TD><B>Height:</B></TD><TD>{$block["height"]}</TD></TR>";
print "<TR><TD><B>Previous Block hash:</B></TD><TD>{$block["previousblockhash"]}</TD></TR>";
print "<TR><TD><B>Merkle root:</B></TD><TD>{$block["merkleroot"]}</TD></TR>";
print "<TR><TD><B>Difficulty:</B></TD><TD>{$block["difficulty"]}</TD></TR>";

$a = hexdec($blockhash);
$b = hexdec("00000000FFFF0000000000000000000000000000000000000000000000000000");
$adiff = sprintf("%.4f",$b/$a);;
$adiffp = ($adiff/$block["difficulty"])*100;
if ($adiffp > 9000) { $adiffp = ">9000"; }
else { $adiffp = sprintf("%.2f",$adiffp); }

print "<TR><TD><B>Difficulty Achieved:</B></TD><TD>{$adiff} ($adiffp%)</TD></TR>";

print "<TR><TD><B>Bits:</B></TD><TD>{$block["bits"]}</TD></TR>";
$nonce = sprintf("%8x",$block["nonce"]);
print "<TR><TD><B>Nonce:</B></TD><TD>$nonce</TD></TR>";
print "<TR><TD><B>Version:</B></TD><TD>{$block["version"]}</TD></TR>";
$t = date("Y-m-d H:i:s",$block["time"]);
print "<TR><TD><B>Time:</B></TD><TD>$t</TD></TR>";

print "<TR><TD><B>Transactions:</B></TD><TD>$txcount</TD></TR>";
print "<TR><TD><B>Size:</B></TD><TD>{$block["size"]} bytes</TD></TR>";
print "<TR><TD><B>Coinbase transaction id:</B></TD><TD>{$block["tx"][0]}<BR><A HREF=\"http://blockchain.info/tx/{$block["tx"][0]}\" TARGET=\"_blank\">View on Blockchain.info</A> - <A HREF=\"http://blockexplorer.com/tx/{$block["tx"][0]}\" TARGET=\"_blank\">View on Blockexplorer.com</A></TD></TR>";


$cbtxid = $block["tx"][0];

$gettxnjson = "{\"method\":\"getrawtransaction\", \"id\":\"1\", \"params\":[\"$cbtxid\",1]}";

$query_hash = "blockinfo.php cbtx " . hash("sha256", "blockinfo.php $gettxnjson");
$gettxn = apc_fetch($query_hash);
if (!isset($gettxn["result"])) {
	$gettxn = my_curl_request($bitcoinrpcurl, $gettxnjson);
	apc_store($query_hash, $gettxn, 864000);
}


$cbtx = $gettxn["result"];

$cbtext = $cbtx["vin"][0]["coinbase"];

$cbouts = count($cbtx["vout"]);
print "<TR><TD><B>Addresses paid in coinbase tx:</B></TD><TD>$cbouts</TD></TR>";

print "</TABLE>";

print "<h2>Payouts in Coinbase Transaction</h2>";

print "<TABLE BORDER=1>";

$failsafe = 0;
$total = 0;

for($i=0;$i<$cbouts;$i++) {

	$out = $cbtx["vout"][$i];


	$ammt = $out["value"];
	$pammt = prettySatoshis($ammt*100000000);
	$addr = $out["scriptPubKey"]["addresses"][0];

	$nickname = ""; $dolink = 1;

	if (array_key_exists($addr,$specialaddrs)) {
		$nickname = $specialaddrs[$addr];
		$dolink = 0;
	}

	# Do this before custom nicknames, obviously
	if (strpos($nickname,'Failsafe Notification') !== false) {
		$failsafe = 1;
	}

	if ($nickname == "") {
		$nickname = get_nickname($link,get_user_id_from_address($link,$addr));
	}

	if ($nickname != "") {
		if ($dolink == 1) {
			$address = "<A HREF=\"../userstats.php/$addr\">$nickname<BR><FONT SIZE=\"-3\">($addr)</FONT></A>";
		} else {
			$address = "$nickname<BR><FONT SIZE=\"-3\">($addr)</FONT>";
		}
	} else {
		$address = "<A HREF=\"../userstats.php/$addr\">$addr</A>";
	}
	print "<TR HEIGHT=\"38\" $oclass><TD>$address</TD><TD>$pammt</TD></TR>";
	$total += $ammt*100000000;

}

print "</TABLE>";

if ($block["height"] >= 261279) {
	# TODO: Make compliant with reward halving
	$fees = $total - 2500000000;
	$rf = 0;
	if ($fees > 500000000) {
		$fees = 500000000;
		$rf = 1;
	}
	print "<BR>Block transactions fees put towards share log: ".prettySatoshis($fees)."<BR>";
	if ($rf == 1) {
		print "Note: Block had an unusually large amount of transaction fees.  Some fees may have been held from immediate payout pending investigation into their origin.<BR>";
	}
}



if ($failsafe) {
print "<BR><B>Note:</B> This block did not contain automated miner payouts due to a reward system failsafe trigger, and instead paid the pool's offline wallet for use in a standard payout later.<BR>This <B>is normal</B> and happens occasionally when one or more variables cause the reward system to temporarily remove automatic payouts from the coinbase transaction being mined (slight delay making payouts stale, quick network blocks in succession, quick pool blocks in succession, etc).<BR>Be assured that funds paid to the pool offline wallet will be used to pay miners manually soon after this block confirms.<BR>";
}

print_stats_bottom();

?>
