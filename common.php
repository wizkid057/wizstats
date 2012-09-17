<?php
#    wizstats - bitcoin pool web statistics
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

print("<HTML>
<HEAD>
<TITLE>".$GLOBALS["titleprepend"]."Eligius Pool Statistics".$GLOBALS["titleappend"]."</TITLE>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."dygraph-combined.js\"></script>
<link rel=\"stylesheet\" href=\"blocklist.css\" type=\"text/css\">
</HEAD>
<BODY BGCOLOR=\"#FFFFFF\" TEXT=\"#000000\" LINK=\"#0000FF\" VLINK=\"#0000FF\" ALINK=\"#FF0000\">
<H2><A HREF=\"".$GLOBALS["urlprefix"]."\">Eligius Pool Statistics</A></H2>");
}


function print_stats_bottom() {


print("<BR><HR>");
print("<H3>MUCH MORE TO COME - PLEASE BE PATIENT</H3>
<BR>
I'm working as quickly as I can to get these stats much more useful and presentable, but it is a time consuming process.<BR>
Any donations will help me dedicate more time to development and would be greatly appreciated: <B><I>1Stats</I>gBq3C8PbF1SJw487MEUHhZahyvR</B><BR><BR>
Thanks for using the new stats!<bR>
<I>-wizkid057</I><BR>
");
print("<A HREF=\"".$GLOBALS["urlprefix"]."\">&lt;-- Back to Main Stats Page</A>");
print("</BODY></HTML>");

}
