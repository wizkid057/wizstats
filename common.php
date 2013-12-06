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


function print_stats_top() {

	$localtitleprepend = ""; $localtitleappend = ""; $localheadextras = ""; $localbodytags = "";
	if (isset($GLOBALS["titleprepend"])) { $localtitleprepend = $GLOBALS["titleprepend"]; }
	if (isset($GLOBALS["titleappend"])) { $localtitleappend = $GLOBALS["titleappend"]; }
	if (isset($GLOBALS["headextras"])) { $localheadextras = $GLOBALS["headextras"]; }
	if (isset($GLOBALS["bodytags"])) { $localbodytags = $GLOBALS["bodytags"]; }
	if (isset($GLOBALS["ldmain"])) { $ldmain = $GLOBALS["ldmain"]; }


	include("instant_livedata.php");

	$GLOBALS["netdiff"] = $netdiff;
	$GLOBALS["phash"] = $phash;
	$GLOBALS["sharesperunit"] = $sharesperunit;

	$roundduration = format_time($roundduration);
	$liveluck = round(($netdiff/$roundshares)*100);
	if ($liveluck > 9999) { $liveluck = ">9999%"; }

	if (!isset($ldmain)) { $ldmain = ""; }

	$rnd = (rand() * rand() + rand());

print("<HTML>
<HEAD>
<TITLE>".$localtitleprepend.$GLOBALS["poolname"]." Pool Statistics".$localtitleappend."</TITLE>
<meta http-equiv=\"X-UA-Compatible\" content=\"IE=Edge,chrome=1\">
<!--[if lt IE 9]><script src=\"".$GLOBALS["urlprefix"]."IE9.js\"></script><![endif]-->
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."dygraph-combined.js\"></script>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."jquery.js\"></script>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."sortable.js\"></script>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."instantscripts.php/livedata$ldmain.js?rand=$rnd\"></script>
<!--[if IE]><script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."excanvas.js\"></script><![endif]-->
<link rel=\"stylesheet\" type=\"text/css\" href=\"".$GLOBALS["urlprefix"]."stats-style.css\">
".$localheadextras."
</HEAD>
<BODY BGCOLOR=\"#FFFFFF\" TEXT=\"#000000\" LINK=\"#0000FF\" VLINK=\"#0000FF\" ALINK=\"#D3643B\" onLoad=\"initShares();\" ".$localbodytags.">
<div id=\"wrapper\">
<div id=\"Eligius-Title\">
	<H2><A HREF=\"".$GLOBALS["urlprefix"]."\">".$GLOBALS["poolname"]." Pool Statistics</A></H2><!--[if IE]><BR><![endif]-->
	<h4>Donations to help stats development:<BR><B>1Stats</B>Qytc7UEZ9sHJ9BGX2csmkj8XZr2</h4>
</div>
<div id=\"luck\">
<TABLE class=\"lucktable\" width=\"100%\">
<TR>
<TD width=\"30%\" style=\"text-align: left\">Hashrate:</TD><TD width=\"25%\" style=\"text-align: right; border-right:1px dotted #CCCCCC; padding-right: 3px; white-space: nowrap;\" id=\"livehashrate\">$phash</TD>
<TD width=\"25%\" style=\"text-align: left\">Round Time:</TD><TD width=\"20%\" style=\"text-align: right\" id=\"roundtime\">$roundduration</TD>
</TR>
<TR>
<TD width=\"30%\" style=\"text-align: left\">Round Shares:</TD><TD width=\"25%\" style=\"text-align: right; border-right:1px dotted #CCCCCC; padding-right: 3px;\" id=\"sharecounter\">$roundshares</TD>
<TD width=\"25%\" style=\"text-align: left\">Round Luck:</TD><TD width=\"20%\" style=\"text-align: right\" id=\"liveluck\">$liveluck%</TD>
</TR>
</TABLE>
</div>
<br>
<br>
<br>
<br>
<div id=\"line\"></div>
<center>
<ul id=\"menu\">
    <li><a href=\"".$GLOBALS["urlprefix"]."\">Home</a></li>
    <li><a href=\"".$GLOBALS["urlprefix"]."mystats.php\">My ".$GLOBALS["poolname"]."</a></li>
    <li><a href=\"".$GLOBALS["urlprefix"]."blocks.php\">Blocks</a></li>
    <li><a href=\"".$GLOBALS["urlprefix"]."topcontributors.php\">Contributors</a></li>
    <li><a target=\"_blank\" href=\"https://github.com/wizkid057/wizstats\">GitHub</a></li>
    <li><a href=\"/\">".$GLOBALS["poolname"]." Homepage</a></li>
</ul>
</center>
<br>
<br>
<br>
<!--[if IE]><H4>This page works best in <A HREF=\"http://www.google.com/chrome\">Google Chrome</A>.  You will not have an optimal experience using Internet Explorer.</H4><![endif]-->
");
}


function print_stats_bottom() {

	$localafterbodyextras = "";
	if (isset($GLOBALS["afterbodyextras"])) { $localafterbodyextras = $GLOBALS["afterbodyextras"]; }

	print("<BR><div id=\"line\">");
	print("<H3>MUCH MORE TO COME - PLEASE BE PATIENT</H3>
<BR>
Source code/bug submissions/feature requests: <A HREF=\"https://github.com/wizkid057/wizstats\">wizkid057/wizstats on github</A><BR>
I'm working as quickly as I can to get these stats much more useful and presentable, but it is a time consuming process.<BR>
Any donations will help me dedicate more time to development and would be greatly appreciated: <B><I>1Stats</I>Qytc7UEZ9sHJ9BGX2csmkj8XZr2</B><BR><BR>
Thanks for using the new stats!<bR>
<I>-wizkid057</I><BR>");

	print("<A HREF=\"".$GLOBALS["urlprefix"]."\">&lt;-- Back to Main Stats Page</A>");
	print("</BODY>".$localafterbodyextras."</HTML>");

}
