<?php
#    wizstats - bitcoin pool web statistics - 1StatsgBq3C8PbF1SJw487MEUHhZahyvR
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
