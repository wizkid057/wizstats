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

include("lib.bitcoin.php");

function bigtolittle($d) {
        $xa = "";
        for($i = 0; $i<strlen($d); $i+=8) {
                $xa .= substr($d,$i+6,2);
                $xa .= substr($d,$i+4,2);
                $xa .= substr($d,$i+2,2);
                $xa .= substr($d,$i,2);
        }
        return $xa;
}

function revhex($d) {
        $xa = "";
        for($i = strlen($d)-2; $i>=0; $i-=2) {
                $xa .= substr($d,$i,2);
        }
        return $xa;
}


function my_curl_request($url, $post_data)
{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 'Content-Type: application/json;');
        curl_setopt($ch, CURLOPT_TRANSFERTEXT, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXY, false);

        // decode result
        $result = @curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
}

function hex2bits($hex) {
        if($hex == '') return '';

        static $trans = array(
                '0' => '0000',
                '1' => '0001',
                '2' => '0010',
                '3' => '0011',
                '4' => '0100',
                '5' => '0101',
                '6' => '0110',
                '7' => '0111',
                '8' => '1000',
                '9' => '1001',
                'a' => '1010',
                'b' => '1011',
                'c' => '1100',
                'd' => '1101',
                'e' => '1110',
                'f' => '1111',
        );

        $bits = '';
        $digits = str_split(strtolower($hex), 1);

        foreach($digits as $d) {
                $bits .= $trans[$d];
        }

        return $bits;
}

function bits2hex($bits) {
        if($bits == '') return '';

        static $trans = array(
                '0000' => '0',
                '0001' => '1',
                '0010' => '2',
                '0011' => '3',
                '0100' => '4',
                '0101' => '5',
                '0110' => '6',
                '0111' => '7',
                '1000' => '8',
                '1001' => '9',
                '1010' => 'a',
                '1011' => 'b',
                '1100' => 'c',
                '1101' => 'd',
                '1110' => 'e',
                '1111' => 'f',
        );

        $hex = '';
        $digits = str_split($bits, 4);

        foreach($digits as $d) {
                $hex .= $trans[$d];
        }

        return $hex;
}


function compatible_gzinflate($gzData) {

if ( substr($gzData, 0, 3) == "\x1f\x8b\x08" ) {
        $i = 10;
        $flg = ord( substr($gzData, 3, 1) );
        if ( $flg > 0 ) {
                if ( $flg & 4 ) {
                        list($xlen) = unpack('v', substr($gzData, $i, 2) );
                        $i = $i + 2 + $xlen;
                }
                if ( $flg & 8 )
                        $i = strpos($gzData, "\0", $i) + 1;
                if ( $flg & 16 )
                        $i = strpos($gzData, "\0", $i) + 1;
                if ( $flg & 2 )
                        $i = $i + 2;
        }
        return @gzinflate( substr($gzData, $i, -8) );
} else {
        return false;
}
}


function get_stats_cache($link, $type, $hash) {

	#$var = apc_fetch("wizstats_cache_".$type."_".$hash);
	#if ($var != FALSE) { return $var; }
	#return "";

	$sql = "select * from ".$GLOBALS["psqlschema"].".stats_cache where type_id=$type and query_hash='$hash' and expire_time > NOW() limit 1;";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	if ($numrows) {
		$row = pg_fetch_array($result, 0);
		return base64_decode($row["data"]);
	}
	return "";

}

function set_stats_cache($link, $type, $hash, $data, $expireseconds) {

	#apc_store("wizstats_cache_".$type."_".$hash,$data, $expireseconds);

	# clean cache
	$sql = "delete from ".$GLOBALS["psqlschema"].".stats_cache where expire_time < NOW();";
	$result = pg_exec($link, $sql);

	$b64data = pg_escape_string($link,base64_encode($data));
	$sql = "insert into ".$GLOBALS["psqlschema"].".stats_cache (query_hash, type_id, create_time, expire_time, data) VALUES ('$hash', $type, NOW(), NOW()+'$expireseconds seconds', '$b64data')";
	$result = pg_exec($link, $sql);


}

function update_stats_cache($link, $type, $hash, $data, $expireseconds) {

	$b64data = pg_escape_string($link,base64_encode($data));
	$sql = "update ".$GLOBALS["psqlschema"].".stats_cache set create_time=NOW(), expire_time=NOW()+'$expireseconds seconds', data='$b64data' where type_id=$type and query_hash='$hash'";
	$result = pg_exec($link, $sql);


}

// src: stackoverflow
function format_time($t,$f=':') // t = seconds, f = separator 
{
  return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
}

function blend_colours($a, $b, $bOpacity)
{
	$aOpacity = 1 - $bOpacity;
	$c = array(0,0,0);
	for ($i = 0; $i < 3; ++$i)
		$c[$i] = ($a[$i] * $aOpacity) + ($b[$i] * $bOpacity);
	return $c;
}

function csscolour($c)
{
	return "rgb(".floor($c[0]).",".floor($c[1]).",".floor($c[2]).")";
}

function verifymessage($bcaddr, $signature, $msg) {

	$json = "{\"method\":\"verifymessage\", \"id\":\"1\", \"params\":[\"$bcaddr\",\"$signature\",\"$msg\"]}";
	$response = my_curl_request($GLOBALS["sigbitcoinrpcurl"], $json);

	if ($response["result"] == "true") {
		return 1;
	}
	return 0;

}

function get_wherein_list_from_worker_data($worker_data) {
	$wherein = "(";
	foreach ($worker_data as $id => &$worker) {
		$wherein .= $id.",";
	}
	return substr($wherein,0,-1).")";
}


function get_worker_data_from_user_id($link, $user_id) {
	# assume $user_id is the first user_id in the database 
	# set a reasonable limit on worker count

	$query_hash = "wizstats_workerlist ".hash("sha256", "workerlist for id $user_id");

	if($worker_data = apc_fetch($query_hash)) {
		return $worker_data;
	} else {

		$sql = "select * from public.users where keyhash=(select keyhash from public.users where id=$user_id) order by id asc limit 128";
		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		if ($numrows > 0) {
			# should always be at least 1...
			$worker_data = array();
			for($ri=0;$ri<$numrows;$ri++) {
				$row = pg_fetch_array($result, $ri);
				if (strlen($row["workername"]) > 0) {
					$wname = $row["workername"];
				} else {
					$wname = "default";
				}
				$worker_data[$row["id"]] = $wname;
			}
			apc_store($query_hash, $worker_data, 300);
			return $worker_data;
		}
		else {
			return NULL;
		}
	}


}

function get_user_id_from_address($link, $addr) {

	$query_hash = "util.php user_id of $addr";

	if ($user_id = apc_fetch($query_hash)) {
		return $user_id;
	} else {
		$bits =  hex2bits(\Bitcoin::addressToHash160($addr));
		$sql = "select id from public.users where keyhash='$bits' order by id asc limit 1";
		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		if ($numrows > 0) {
			$row = pg_fetch_array($result, 0);
			apc_store($query_hash, $row["id"], 86400);
			return $row["id"];
		}
		apc_store($query_hash, 0, 86400);
		return 0;
	}
}


function get_nickname($link, $user_id) {

	$nickname = "";
	$query_hash = "wizstats_nickname ".hash("sha256", "userstats.php nickname for id $user_id");

	if($nickname = apc_fetch($query_hash)) {
	} else {
		$sql = "select * from {$GLOBALS["psqlschema"]}.stats_mystats where server={$GLOBALS["serverid"]} and user_id=$user_id order by time desc limit 1";
		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		if ($numrows) {
			$row = pg_fetch_array($result, 0);
			if (isset($row["signed_options"])) {
				$msg = $row["signed_options"];
				$msghead = "My ".($GLOBALS["poolname"])." - ";
		                $msgvars = substr($msg,strlen($msghead)+26,10000);
		                $msgvars = str_replace(" ","&",$msgvars);
		                parse_str($msgvars, $msgvars_array);

				if (isset($msgvars_array["Nickname"])) {
					$nickname = htmlspecialchars($msgvars_array["Nickname"]);
				} else {
					$nickname = "No nickname";
				}
			} else {
				$nickname = "No nickname";
			}
			apc_store($query_hash, $nickname, 3600);
		} else {
			$nickname = "No nickname";
		}
	}

	if ($nickname == "No nickname") { 
		$nickname = ""; 
	}

	return $nickname;
}

function get_options($link, $user_id) {

	$query_hash = "wizstats_options ".hash("sha256", "userstats.php options for id $user_id");

	if($options = apc_fetch($query_hash)) {
	} else {
		$sql = "select * from {$GLOBALS["psqlschema"]}.stats_mystats where server={$GLOBALS["serverid"]} and user_id=$user_id order by time desc limit 1";
		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		if ($numrows) {
			$row = pg_fetch_array($result, 0);
			$options = array();
			if (isset($row["signed_options"])) {
				$msg = $row["signed_options"];
				$msghead = "My ".($GLOBALS["poolname"])." - ";
		                $msgvars = substr($msg,strlen($msghead)+26,10000);
		                $msgvars = str_replace(" ","&",$msgvars);
		                parse_str($msgvars, $options);
			}
			apc_store($query_hash, $options, 3600);
		}
	}

	return $options;
}


define( 'LOCK_FILE', "/tmp/".basename( isset($argv[0])?$argv[0]:$_SERVER["SCRIPT_NAME"], ".php" ).".lock" );

function isLocked()
{
	# If lock file exists, check if stale.  If exists and is not stale, return TRUE
	# Else, create lock file and return FALSE.

	if( file_exists( LOCK_FILE ) ) {
		# check if it's stale
		$lockingPID = trim( file_get_contents( LOCK_FILE ) );

		# Get all active PIDs.
		$pids = explode( "\n", trim( `ps -e | awk '{print $1}'` ) );

		# If PID is still active, return true
		if( in_array( $lockingPID, $pids ) )  return true;

		# Lock-file is stale, so kill it.  Then move on to re-creating it.
		echo "Removing stale lock file.\n";
		unlink( LOCK_FILE );
	}

	file_put_contents( LOCK_FILE, getmypid() . "\n" );
	return false;
}

function getblock_rpc($blockhash) {
	$json = "{\"method\":\"getblock\", \"id\":\"1\", \"params\":[\"$blockhash\"]}";
	$response = my_curl_request($GLOBALS["bitcoinrpcurl"], $json);
	return $response;
}

function getrawtransaction_rpc($hash) {
	$json = "{\"method\":\"getrawtransaction\", \"id\":\"1\", \"params\":[\"$hash\",1]}";
	$response = my_curl_request($GLOBALS["bitcoinrpcurl"], $json);
	return $response;
}

