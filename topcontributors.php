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



require_once 'config.php';
if (!isset($link)) { $link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }



$sql = "select (sum(accepted_shares)*pow(2,32))/10800 as avghash,keyhash from stats_shareagg left join users on user_id=users.id where server=$serverid and time > to_timestamp((date_part('epoch', (select time from stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'3 hours'::interval)::integer / 675) * 675) and accepted_shares > 0 group by keyhash order by avghash desc $minilimit;";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);


if (!isset($subcall)) {
	$titleprepend = "Contributors - ";
	print_stats_top();
}

print "<TABLE BORDER=1>";


for($ri = 0; $ri < $numrows; $ri++) {

                        $row = pg_fetch_array($result, $ri);
			$phash = prettyHashrate($row["avghash"]);
if (isset($row['keyhash'])) {
                $address =  \Bitcoin::hash160ToAddress(bits2hex($row['keyhash']));
		$address = "<A HREF=\"userstats.php/$address\">$address</A>";
        } else {
                 $address = "(Unknown user)";
        }

			print "<TR><TD>$address</TD><TD>$phash</TD></TR>";
}
print "</TABLE>";

if (!isset($subcall)) {
	print_stats_bottom();
}

?>

