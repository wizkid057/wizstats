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

if (isset($_GET["storecookie"])) { setcookie("u", $_GET["u"], time()+86400*365); $u = $_GET["u"];}
else { if (isset($_COOKIE["u"])) { setcookie("u", $_COOKIE["u"], time()+86400*365); $u = $_COOKIE["u"]; } }

if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

$titleprepend = "My $poolname - ";

if ((isset($_GET["cmd"])) && (strlen($_GET["cmd"]) > 0)) {
	$cmd = $_GET["cmd"];
} else {
	$cmd = "menu";
}

if ($cmd == "logout") {
	setcookie ("u", "", time() - 3600);
	unset($_COOKIE["u"]);
	unset($_GET["u"]);
}


print_stats_top();

print "<H2>*** Panel is in BETA.  Not all options are supported yet. ***</H2>You may set all options now, and they will take effect as backend support is completed!<BR><BR><HR><BR>";

if ($cmd == "logout") {
	print "Logout successful.<BR>";
}

?>

<?php
$nouser = 0;
$reason = "";
if (((!isset($_COOKIE["u"])) && (!isset($_GET["u"]))) || ( (isset($_GET["u"])) && (strlen($_GET["u"]) == 0) ) ) {
	$nouser = 1;
} else {
	$u = "";
	if (isset($_GET["u"])) { $u = $_GET["u"]; } else { if (isset($_COOKIE["u"])) { $u = $_COOKIE["u"]; } }

	$user_id = get_user_id_from_address($link, $u);

	if (!$user_id) {
		$nouser = 1;
		$reason = "$u was not found in database.<BR>";
	}
}


if ($cmd == "switchaddr") {
	$nouser = 1;
}


if ($nouser == 1) {

	if ($cmd != "switchaddr") {
	?>

	<H2>No address sent for <I>My Stats</I> page</H2><BR>
	<?php 
	}
	?>
	<?php echo $reason; ?>
	To use <I>My Stats</I> you must specify your <?php echo $poolname; ?> mining address.<BR>
	<BR>

	<FORM METHOD="GET">Mining Address: <INPUT TYPE="text" name="u" size=40 maxlength=512><BR>
	<input type="checkbox" name="storecookie" CHECKED> Store mining address in browser cookie? (as of now broken unless checked)<BR>
	<input type="submit" value="Proceed!">
	</FORM>

	<?php
	print_stats_bottom(); exit();
}


# ok, valid user in $u
print "Welcome, $u! <BR>";


if ($cmd) {

	if ($cmd == "menu") {
		print "<A HREF=\"userstats.php/$u\">My User Stats Page</A><BR>\n";
		print "<A HREF=\"?cmd=options\">Configurable Options</A><BR>\n";
		print "<A HREF=\"?cmd=switchaddr\">Switch Mining Address</A><BR>\n";
	}

	if ($cmd == "submitsig") {
		$sig = $_GET["sig"];
		$msg = $_GET["msg"];

		# msg format
		# My Eligius - 2013-03-27 00:11:22 - minimumpayout=1.23456789&nickname=wizkid057&generationpayout=1

		$msghead = "My ".$poolname." - ";

		$validate = 1;

		if (substr($msg,0,strlen($msghead)) != $msghead) {
			print "Invalid Message! ";
			$validate = 0;
		} 

		$msgdate = substr($msg,strlen($msghead),19);

		$msgdateunix = strtotime ($msgdate." UTC");
		if ($msgdateunix > time()+86400) {
			print "Invalid timestamp! ";
			$validate = 0;
		}
		if ($msgdateunix < time()-86400) {
			print "Invalid timestamp! ";
			$validate = 0;
		}

		$msgvars = substr($msg,strlen($msghead)+26,10000);
		$msgvars = str_replace(" ","&",$msgvars);
		parse_str($msgvars, $msgvars_array);

		if (count($msgvars_array) == 0) {
			print "No variables set! Set at least one variable! ";
			$validate = 0;
		}

		$donatesum = $msgvars_array["Donate_Pool"]+$msgvars_array["Donate_Stats"]+$msgvars_array["Donate_Hosting"];

		if ($donatesum > 100) {
			$validate = 0;
			print "Donations total more than 100%! (While we appreciate the thought, this is invalid...) ";
		}
		if ($donatesum < 0) {
			$validate = 0;
			print "Donations total less than 0%! (Nice try...) ";
		}
		if ($msgvars_array["Donate_Pool"] < 0) {
			$validate = 0;
			print "Donations to pool invalid. ";
		}
		if ($msgvars_array["Donate_Stats"] < 0) {
			$validate = 0;
			print "Donations to stats invalid. ";
		}
		if ($msgvars_array["Donate_Hosting"] < 0) {
			$validate = 0;
			print "Donations to hosting invalid. ";
		}

		if ($donatesum > 100) { $donatesum = 100; }
		if ($donatesum < 0) { $donatesum = 0; }
		$donatesum = "<I>$donatesum%</I>";

		if (($validate) && isset($msgvars_array["Minimum_Work_Diff"]) && ( (filter_var($msgvars_array["Minimum_Work_Diff"], FILTER_VALIDATE_INT) === FALSE) || 
			($msgvars_array["Minimum_Work_Diff"] < 1) || 
			($msgvars_array["Minimum_Work_Diff"] > 65536) || 
			(($msgvars_array["Minimum_Work_Diff"] & ($msgvars_array["Minimum_Work_Diff"]-1)) != 0))) {
			$validate = 0;
			print "Invalid minimum difficulty! (Valid values are powers of two: 1,2,4,8,16,32,etc) ";
		}

		if ((isset($msgvars_array["Minimum_Payout_BTC"])) && ($msgvars_array["Minimum_Payout_BTC"] < 0.01048576)) {
			$validate = 0;
			print "Invalid minimum payout. (Must be 10 TBC (0.01048576 BTC) or greater)";
		}


		if ($validate == 1) {

			$sql = "select date_part('epoch', time)::integer as etime from $psqlschema.stats_mystats where server=$serverid and user_id=$user_id order by id desc limit 1";
			$result = pg_exec($link, $sql);
			$numrows = pg_numrows($result);
			if ($numrows > 0) {
				$row = pg_fetch_array($result, 0);
				$etime = $row["etime"];
				if (($msgdateunix - $etime) < 5) {
					$validate = 0;
					print "Newly signed options must have a timestamp at least 5 seconds newer than previously signed options. ";
				}
			}

		}

		if ($validate == 1) {

			$sigok = 0;
			if ((strlen($sig) > 35) && (strlen($msg) > 0)) {
				$sigok = verifymessage($u, $sig, $msg);
			}
			if ($sigok) {
				print "Signature passes!";
				$signedoptions = pg_escape_string($link,$msg);

				$signature = pg_escape_string($link,$sig);
				$sql = "insert into $psqlschema.stats_mystats (server, user_id, time, signed_options, signature) VALUES ($serverid, $user_id, to_timestamp($msgdateunix), '$signedoptions', '$signature');";
				$result = pg_exec($link, $sql);
				#print "SQL: $sql";
			}
			else {
				print "Signature fails!";
			}
		}


		$cmd = "options"; # fall through

	}

	if ($cmd == "options") {

		if (!isset($msgvars_array)) {
			$sql = "select * from $psqlschema.stats_mystats where server=$serverid and user_id=$user_id order by id desc limit 1";
			$result = pg_exec($link, $sql);
			$numrows = pg_numrows($result);
			if ($numrows > 0) {
				$row = pg_fetch_array($result, 0);
				$msg = $row["signed_options"];
				$msghead = "My ".$poolname." - ";
				$msgdate = substr($msg,strlen($msghead),19);
				$msgdateunix = strtotime ($msgdate." UTC");
				$msgvars = substr($msg,strlen($msghead)+26,10000);
				$msgvars = str_replace(" ","&",$msgvars);
				parse_str($msgvars, $msgvars_array);
				$sig = $row["signature"];
			}
		} else {
			$msg = $_GET["msg"];
			$sig = $_GET["sig"];
		}


		?>

		<SCRIPT language="javascript">
		<!--

			function js_yyyy_mm_dd_hh_mm_ss () {
				now = new Date();
				now = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds());
				year = "" + now.getFullYear();
				month = "" + (now.getMonth() + 1); if (month.length == 1) { month = "0" + month; }
				day = "" + now.getDate(); if (day.length == 1) { day = "0" + day; }
				hour = "" + now.getHours(); if (hour.length == 1) { hour = "0" + hour; }
				minute = "" + now.getMinutes(); if (minute.length == 1) { minute = "0" + minute; }
				second = "" + now.getSeconds(); if (second.length == 1) { second = "0" + second; }
				return year + "-" + month + "-" + day + " " + hour + ":" + minute + ":" + second + " UTC";
			}

			function updateOptionsMessage() {
				//alert(document.optionsform.nickname.value);
				var str = "My <?php echo $poolname; ?> - " + js_yyyy_mm_dd_hh_mm_ss() + " - ";
				//if (document.optionsform.nickname.value.length > 0) { str = str + "nickname=" + encodeURIComponent(document.optionsform.nickname.value) + "&"; }
				//if (document.optionsform.minimumpayout.value.length > 0) { str = str + "minimumpayout=" + encodeURIComponent(document.optionsform.minimumpayout.value) + "&"; }
				var elem = document.optionsform.elements;
				for(var i = 0; i < elem.length; i++) {
					if (elem[i].value.length > 0) { str = str + elem[i].name + "=" + encodeURIComponent(elem[i].value) + " "; }
				}

				str = str.substring(0, str.length - 1);
				document.sigform.msg.value = str + "";
				document.getElementById("msgdiv").innerHTML= "<I>" + str + "</I>";
				var d1 = parseFloat(document.optionsform.Donate_Pool.value);
				var d2 = parseFloat(document.optionsform.Donate_Stats.value);
				var d3 = parseFloat(document.optionsform.Donate_Hosting.value)
				var donatetotal = 0;
				if (d1 > 0) { donatetotal += d1; }
				if (d2 > 0) { donatetotal += d2; }
				if (d3 > 0) { donatetotal += d3; }
				if (donatetotal > 100) { donatetotal = 100; }
				document.getElementById("totaldonate").innerHTML = "<I>" + donatetotal + "%</I>";
			}

		-->
		</SCRIPT>

		<BR><B>You must use a bitcoin client which supports standard signatures, or MtGox, for your mining address to sign options!</b><BR>
		<BR><H3><U>Options Form</U></H3><SMALL>All fields are optional. Leaving a field blank will result in the pool default for the setting being applied.</SMALL><BR><BR>
		<FORM name="optionsform" onsubmit="return false;">
		<TABLE BORDER=0>
		<TR><TD><B>Nickname</B>:</TD><TD><INPUT TYPE="TEXT" name="Nickname" size=32 maxlength=32 value="<?php echo htmlspecialchars($msgvars_array["Nickname"]); ?>" onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()"> (Default: <?php echo $u; ?>)</TD></TR>
		<TR><TD><B>Minimum Payout</B>:</TD><TD><INPUT TYPE="TEXT" name="Minimum_Payout_BTC" size=12 value="<?php echo  htmlspecialchars($msgvars_array["Minimum_Payout_BTC"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()"> BTC (Default: 0.16777216, Minimum: 0.01048576 [10 TBC])</TD></TR>
		<TR><TD><B>Optional Donation %s</B>:</TD><TD></TD></TR>
		<TR><TD style="text-align:right;"><SMALL>Pool Management:</SMALL></TD><TD><INPUT TYPE="TEXT" name="Donate_Pool" size=6 value="<?php echo htmlspecialchars($msgvars_array["Donate_Pool"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()">% (Default: 0.00%)</TD></TR>
		<TR><TD style="text-align:right;"><SMALL>Stats Development:</SMALL></TD><TD><INPUT TYPE="TEXT" name="Donate_Stats" size=6 value="<?php echo htmlspecialchars($msgvars_array["Donate_Stats"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()">% (Default: 0.00%)</TD></TR>
		<TR><TD style="text-align:right;"><SMALL>Pool Hosting:</SMALL></TD><TD><INPUT TYPE="TEXT" name="Donate_Hosting" size=6 value="<?php echo htmlspecialchars($msgvars_array["Donate_Hosting"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()">% (Default: 0.00%)</TD></TR>
		<TR><TD style="text-align:right;"><SMALL><B>Total</B></SMALL></TD><TD id="totaldonate"><?php echo $donatesum; ?></TD></TR>
		<TR><TD><B>NMC Merged Mining Addr</B>:</TD><TD><INPUT TYPE="TEXT" name="NMC_Address" size=35 maxlength=35 value="<?php echo htmlspecialchars($msgvars_array["NMC_Address"]); ?>" onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()"> (Default: Blank)</TD></TR>
		</TABLE></FORM>

		<HR><BR>

		<FORM METHOD="GET" name="sigform">
		<B>Message to Sign with <?php echo $u; ?>:<BR></B>
		<div id="msgdiv"><?php echo htmlspecialchars($msg); ?></div><BR>
		<input type="text" name="msg" size="128" value="<?php echo htmlspecialchars($msg); ?>"><BR>
		<B>Signature</B>:<BR><INPUT TYPE="text" size="128" name="sig" value="<?php echo htmlspecialchars($sig); ?>"><BR>
		<input type="submit" value="Submit Changes!">
		<input type="hidden" name="cmd" value="submitsig">
		<input type="hidden" name="u" value="<?php echo $u; ?>">
		</FORM>

		Using an MtGox wallet address? Copy your message to your clipboard, then <A TARGET="_blank" HREF="https://www.mtgox.com/sign-message?signin[bcaddr]=<?php echo $u; ?>&signin[msg]=&signin[callback]=http://eligius.st/~wizkid057/newstats/mystats.php?cmd=options">Click here for MtGox's message signing interface</A><BR><BR>

		<BR><H3><U><FONT COLOR="RED">WARNING - READ BEFORE SUBMITTING</FONT></U></H3>
		<B>Quick terms: By submitting a valid signature for your mining address, you are agreeing to these terms.<BR>Submitting the changes with a valid signature will immediately save the changes to the server.<BR>
		To undo any changes, you will have to submit new changes with a new signature.<BR>
		The pool is *not* responsible for any properly signed settings which are incorrect/undesired.<BR></B>
		<BR>Some changes can take up to an hour to take effect.  All changes take effect at that time.  No changes are retroactive and all only apply from the time they are updated.  The pool reserves the right to remove any nickname it feels is inappropriate, at it's sole discretion.  This page is part of the public pool stats and anyone is able to view the settings here, along with the most recent signed message and signature for open verification.  However, settings can not be changed without a new valid signature.<BR><BR>
		Option specific notes:<BR>
		* Nickname - Please keep it clean.  No URLs, ads, etc.  This will be displayed on your userstats page under your address.<BR><BR>
		* Minimum Work Difficulty - This option will have the pool use a best-effort attempt at serving work at or above this difficulty to your workers.  Due to the way Stratum handles authentication and work processing, you may still receive some difficulty 1 work at first because work is given before the pool knows who you are.  The pool reserves the right to reset this value to the pool default for any miner at any time at it's sole discretion.<BR><BR>
		* Minimum Payout - This option does not take effect instantly.  You may still be subject to a previously set (or default) value for up to 24 hours after submitting changes.<BR><BR>
		* Optional Donation %s - The various donation &quot;buckets&quot; each have their own bitcoin address which will be paid by the pool based on the standard payout queue.<BR><BR>
		* NMC Merged Mining Addr - Namecoin payouts for your mining will go to this address.
		<HR>
		<?php
	}


}



?>

<A HREF="mystats.php?cmd=menu">My Stats Menu</A><BR>
<A HREF="mystats.php?cmd=logout">Log Out</A><BR>

<?php print_stats_bottom(); ?>

