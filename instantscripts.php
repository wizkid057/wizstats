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

require_once "includes.php";

if (!isset($_SERVER['PATH_INFO'])) { exit(); }

if (($_SERVER['PATH_INFO'] == "/livedata-main.js") || ($_SERVER['PATH_INFO'] == "/livedata.js")) {

	if ($_SERVER['PATH_INFO'] == "/livedata-main.js") { $main = 1; } else { $main = 0; }

	header("Content-type: application/javascript");
	header("Cache-Control: max-age=60");

	include("instant_livedata.php");

	$polltimer = 60000;
	$fullpolltimer = 601000;


	print "
	var intCountShares = $roundshares;
	var intSharesPerUnit = ($sharesperunit) * 0.02;
	var intCurrentBlockHeight = $blockheight;
	var intCurrentBlockConfirms = $latestconfirms;
	var latestBlockHeight = $blockheight;
	var latestBlockConfirms = $latestconfirms;
	var intRoundDuration = $roundduration;
	var prettyRoundDuration = '';
	var prettyHashrate = '$phash';
	var networkDifficulty = $netdiff;
	var networkDifficulty1000 = networkDifficulty * 1000;
	var dom_livehashrate;
	var dom_liveluck;
	var dom_roundtime;
	var dom_sharecounter;
	var countSharesDelay;
	var countSharesDelayNext = 41;

	function updateRoundDuration()
	{
		prettyRoundDuration = secondsToHms(intRoundDuration);
		dom_roundtime.data = prettyRoundDuration;
		intRoundDuration++;
	}

	function updateLuck()
	{
		var luck = Math.round( networkDifficulty1000 / intCountShares ) / 10;
		var newstr;
		if (luck > 9999.9) {
			newstr = '>9999.9%';
		} else {
			if (luck >= 1000.0) {
				newstr = Math.round(luck) + '%';
			} else {
				newstr = luck + '%';
			}
		}
		if (dom_liveluck.data != newstr)
			dom_liveluck.data = newstr;
	}

	function updatePerSecond()
	{
		updateRoundDuration();
		updateLuck();
		setTimeout(\"updatePerSecond()\",1000);
	}

	function updateSharesData()
	{

		\$.getJSON(\"".$GLOBALS["urlprefix"]."instant.php/livedata.json?rand=\" + Math.random(),
			function(data){
				intCountShares = data.roundsharecount;
				intSharesPerUnit = data.sharesperunit * 0.02;
				latestBlockHeight = data.lastblockheight;
				latestBlockConfirms = data.lastconfirms;
				intRoundDuration = data.roundduration;
				prettyHashrate = data.hashratepretty;
				networkDifficulty = data.network_difficulty;
				networkDifficulty1000 = networkDifficulty * 1000;
				
				dom_livehashrate.data = prettyHashrate;
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
		intCountShares += intSharesPerUnit * countSharesDelay;
		dom_sharecounter.data = Math.round(intCountShares);
		countSharesDelay = countSharesDelayNext;
		setTimeout(\"countShares()\", countSharesDelay);
	}

	$(window).blur(function() {
		countSharesDelayNext = 1318;
	})

	$(window).focus(function() {
		countSharesDelayNext = 41;
	})

	function initShares()
	{
		dom_livehashrate = document.getElementById('livehashrate').childNodes[0];
		dom_liveluck = document.getElementById('liveluck').childNodes[0];
		dom_roundtime = document.getElementById('roundtime').childNodes[0];
		dom_sharecounter = document.getElementById('sharecounter').childNodes[0];
		
		updateSharesData();
		updatePerSecond();
		countShares(); 
";
if ($main) {
print "
		setTimeout(\"updateBlockTable(1)\",$fullpolltimer);
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
		return ((h > 0 ? h + \":\" : \"\") + (m >= 0 ? (h > 0 && m < 10 ? \"0\" : \"\") + m + \":\" : \"0:\") + (s < 10 ? \"0\" : \"\") + s);
	}

";
}

