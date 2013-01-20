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

require_once "includes.php";

if (!isset($_SERVER['PATH_INFO'])) { exit(); }

if (($_SERVER['PATH_INFO'] == "/livedata-main.js") || ($_SERVER['PATH_INFO'] == "/livedata.js")) {

	if ($_SERVER['PATH_INFO'] == "/livedata-main.js") { $main = 1; } else { $main = 0; }

	header("Content-type: application/javascript");

	include("instant_livedata.php");

	$polltimer = 60000;
	$fullpolltimer = 601000;


	print "
	var intCountShares = $roundshares;
	var intSharesPerUnit = $sharesperunit;
	var intCurrentBlockHeight = $blockheight;
	var intCurrentBlockConfirms = $latestconfirms;
	var latestBlockHeight = $blockheight;
	var latestBlockConfirms = $latestconfirms;
	var intRoundDuration = $roundduration;
	var prettyRoundDuration = '';
	var prettyHashrate = '$phash';
	var networkDifficulty = $netdiff;

	function updateRoundDuration()
	{
		prettyRoundDuration = secondsToHms(intRoundDuration);
		intRoundDuration++;
		setTimeout(\"updateRoundDuration()\",1000);
	}

	function updateSharesData()
	{

		\$.getJSON(\"".$GLOBALS["urlprefix"]."instant.php/livedata.json\",
			function(data){
				intCountShares = data.roundsharecount;
				intSharesPerUnit = data.sharesperunit;
				latestBlockHeight = data.lastblockheight;
				latestBlockConfirms = data.lastconfirms;
				intRoundDuration = data.roundduration;
				prettyHashrate = data.hashratepretty;
				networkDifficulty = data.network_difficulty;
			});

		setTimeout(\"updateSharesData()\",$polltimer);
		setTimeout(\"checkNewInfo()\",500);
	}

	function checkNewInfo()
	{
";


if ($main) {
print "		if (latestBlockHeight != intCurrentBlockHeight) {
			// new block found... add it!
			\$.getJSON(\"".$GLOBALS["urlprefix"]."instant.php/blockinfo.json?height=\"+latestBlockHeight+\"&cclass=\"+\$(\"#blocklisttable tr:last\").attr('class'),
				function(data){
					\$(\"#blocklistheaderid\").after(data.blockrow);
					\$(\"#blocklisttable tr:last\").remove();
					intCurrentBlockHeight = latestBlockHeight;
				});
		}
";
}

print "
		if (latestBlockConfirms != intCurrentBlockConfirms) {
			// new confirmation data...
			intCurrentBlockConfirms = latestBlockConfirms;
";

if ($main) {
	print "			updateBlockTable(0);\n";
}


print "
		}
	}

	function countShares()
	{
		intCountShares += intSharesPerUnit*5;
		sharecounter.innerHTML = Math.round(intCountShares);
		roundtime.innerHTML = prettyRoundDuration;
		livehashrate.innerHTML = prettyHashrate;
		if ((Math.round( (networkDifficulty / intCountShares )*1000)/10) > 9999.9) {
			liveluck.innerHTML = '>9999.9%';
		} else {
			if ((Math.round( (networkDifficulty / intCountShares )*1000)/10) >= 1000.0) {
				liveluck.innerHTML = Math.round( (networkDifficulty / intCountShares )*100) + '%';
			} else {
				liveluck.innerHTML = Math.round( (networkDifficulty / intCountShares )*1000)/10 + '%';
			}
		}
		setTimeout(\"countShares()\",250);
	}

	function initShares()
	{
		updateSharesData();
		updateRoundDuration();
		countShares(); 
";
if ($main) {
print "
		setTimeout(\"updateBlockTable()\",$fullpolltimer);
";
}
print "
	}

	function updateBlockRow(relem, rblockid) {
";
if ($main) {
print "
			\$.getJSON(\"".$GLOBALS["urlprefix"]."instant.php/blockinfo.json?dbid=\"+rblockid+\"&cclass=\"+\$(relem).attr('class'),
				function(data){
					\$(relem).after(data.blockrow);
					\$(relem).remove();
				});
";
}
print "
	}

	function updateBlockTable(timercall)
	{
";
if ($main) {
print "
		$('#blocklisttable tr').each(function(index, elem) { 
			if (index>0) {
				if (\$(elem).attr('id').substring(0,8) == 'blockrow') {
					var confcell = 'null';
					\$(elem).each(function() { confcell = \$(this).find('.blockconfirms').html(); });
					if (!((confcell == 'Confirmed') || (confcell == 'Stale'))) {
						updateBlockRow(elem,\$(elem).attr('id').substring(8));
					}
				}
			}

		});
		if (timercall) {
			setTimeout(\"updateBlockTable(1)\",$fullpolltimer);
		}
";
}
print "
	}

	function secondsToHms(d) {
		d = Number(d);
		var h = Math.floor(d / 3600);
		var m = Math.floor(d % 3600 / 60);
		var s = Math.floor(d % 3600 % 60);
		return ((h > 0 ? h + \":\" : \"\") + (m > 0 ? (h > 0 && m < 10 ? \"0\" : \"\") + m + \":\" : \"0:\") + (s < 10 ? \"0\" : \"\") + s);
	}

";
}

