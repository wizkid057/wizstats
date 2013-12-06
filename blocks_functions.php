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

function block_table_start($sortable) {
	if ($sortable) {
		return "<TABLE id=\"blocklisttable\" CLASS=\"blocklist sortable\">";
	} else {
		return "<TABLE id=\"blocklisttable\" CLASS=\"blocklist\">";
	}
}

function block_table_end() {
	return "</TABLE>";
}

function block_table_header() {

	$blocks_header = "<thead>";
	$blocks_header .= "<TR id=\"blocklistheaderid\">";
	$blocks_header .= "<TH>Age</TH>";
	$blocks_header .= "<TH>Round Start</TH>";
	$blocks_header .= "<TH>Round Duration</TH>";
	$blocks_header .= "<TH>Accepted Shares</TH>";
	$blocks_header .= "<TH>Difficulty</TH>";
	$blocks_header .= "<TH>Luck</TH>";
	$blocks_header .= "<TH>Hashrate</TH>";
	$blocks_header .= "<TH>Confirmations</TH>";
	$blocks_header .= "<TH>Contributor</TH>";
	$blocks_header .= "<TH>Height</TH>";
	$blocks_header .= "<TH>Block Hash</TH>";
	$blocks_header .= "</TR>";
	$blocks_header .= "</thead>";
	return $blocks_header;

}

function block_table_row($row,$isodd) {

	$blocks_row = "";

	if (isset($row["acceptedshares"])) { $luck = 100 * ($row["network_difficulty"] / $row["acceptedshares"]); } else { $luck = 0; }
	if ($luck > 9000) { $luck = ">9,000%"; } else { $luck = number_format(round($luck,1),1)."%"; }


	$roundstart = substr($row["roundstart"],0,19);
	if ($row["confirmations"] >= 120) { $confs = "Confirmed"; }
	else if ($row["confirmations"] == 0) { $confs = "Stale"; $luck = "n/a"; $roundstart = "<SMALL>(".substr($row["time"],0,19); $roundstart .= ")</SMALL>"; }
	else { $confs = $row["confirmations"]." of 120"; }

	$dbid = $row["blockid"];

	if ($row["confirmations"] == 0) { 
		$blocks_row .= "<TR id=\"blockrow$dbid\" BGCOLOR=\"#FFDFDF\" class=\"$isodd"."blockorphan\">"; 
	}
	else if ($row["confirmations"] >= 120) { 
		$blocks_row .= "<TR id=\"blockrow$dbid\" class=\"$isodd"."blockconfirmed\">"; 
	}
	else { 
		$rowcolour = $isodd ? array(0xd3, 0xeb, 0xe3) : array(0xeb, 0xed, 0xe9);
		$uccolour = array(0xff, 0x7f, 0);
		$rowcolour = blend_colours($uccolour, $rowcolour, $row["confirmations"] / 120);
		$blocks_row .= "<TR class=\"$isodd"."blockunconfirmed\" id=\"blockrow$dbid\" style=\"background-color: ".csscolour($rowcolour)."\">";
	}

	$blocks_row .= "<TD sorttable_customkey=\"".$row["age"]."\" style=\"font-size: 0.9em;\">".prettyDuration($row["age"],false,1)."</TD>";



	$blocks_row .= "<TD style=\"font-size: 0.8em;\">".$roundstart."</TD>";

	if (isset($row["duration"])) {
		list($seconds, $minutes, $hours) = extractTime($row["duration"]);
		$seconds = sprintf("%02d", $seconds);
		$minutes = sprintf("%02d", $minutes);
		$hours = sprintf("%02d", $hours);
		$blocks_row .= "<td sorttable_customkey=\"".$row["duration"]."\" style=\"width: 1.5em;  text-align: right;\">$hours:$minutes:$seconds</td>";

		$hashrate = ($row["acceptedshares"] * 4294967296) / $row["duration"];
		$hashratenum = $hashrate;
		$hashrate = prettyHashrate($hashrate);
		$hashrate = substr($hashrate,0,-2);
	} else {
		$blocks_row .= "<td style=\"text-align: right;\">n/a</td>";
		$hashrate = "n/a";
	}

	$blocks_row .= "<TD style=\"text-align: right;\" sorttable_customkey=\"".$row["acceptedshares"]."\">".($row["acceptedshares"]>0?number_format($row["acceptedshares"]):"n/a")."</TD>";

	$blocks_row .= "<TD style=\"text-align: right;\">".number_format(round($row["network_difficulty"],0))."</TD>";
	$blocks_row .= "<TD style=\"text-align: right;\">".$luck."</TD>";

	$hashratenum = sprintf("%.0f",$hashratenum);

	$blocks_row .= "<TD style=\"text-align: right; font-size: 0.9em;\" sorttable_customkey=\"".$hashratenum."\" >".$hashrate."</TD>";




	$blocks_row .= "<TD class=\"blockconfirms\" style=\"text-align: right;\">".$confs."</TD>";
	if (isset($row['keyhash'])) {
		$fulladdress =  \Bitcoin::hash160ToAddress(bits2hex($row['keyhash']));
		$address = substr($fulladdress,0,10)."...";
	} else {
		$fulladdress = "";
		$address = "(Unknown user)"; 
	}
	$blocks_row .= "<TD style=\"font-family:monospace;\"><A HREF=\"userstats.php/".$fulladdress."\">".$address."</A></TD>";


	if ((isset($row["height"])) && ($row["height"] > 0)) {
		$ht = number_format($row["height"]);
	} else {
		$ht = "n/a";
	}
	$blocks_row .= "<TD style=\"text-align: right;\">$ht</TD>";

	$nicehash = "...".substr($row["blockhash"],46,18);
	$blocks_row .= "<TD style=\"font-family:monospace;\"><A HREF=\"blockinfo.php/".$row["blockhash"]."\">".$nicehash."</A></TD>";
	$blocks_row .= "</TR>";

	return $blocks_row;


}

?>
