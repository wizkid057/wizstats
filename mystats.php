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



require_once 'includes.php';

if (isset($_GET["storecookie"])) { setcookie("u", $_GET["u"], time()+86400*365); $u = $_GET["u"];}
else { if (isset($_COOKIE["u"])) { setcookie("u", $_COOKIE["u"], time()+86400*365); $u = $_COOKIE["u"]; } }

if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

$titleprepend = "My Stats - ";
print_stats_top();


if (!isset($_GET["wizdebug"])) { 
	echo("<BR>This page is under construction! Check back soon! :)<BR>"); 
	print_stats_bottom();
	exit();
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
	$bits =  hex2bits(\Bitcoin::addressToHash160($u));

	$sql = "select id from public.users where keyhash='$bits' order by id asc limit 1";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);

	if (!$numrows) {
		$nouser = 1;
		$reason = "$u was not found in database.<BR>";
	}
}

if ($nouser == 1) {

	?>

	<H2>No username sent for <I>My Stats</I> page</H2><BR>
	<?php echo $reason; ?>
	To use <I>My Stats</I> you must specify your <?php echo $poolname; ?> username (mining address).<BR>
	<BR>

	<FORM METHOD="GET">Username: <INPUT TYPE="text" name="u" size=40 maxlength=512><BR>
	<input type="checkbox" name="storecookie" CHECKED> Store username in browser cookie?<BR>
	<input type="submit" value="Proceed!">
	<input type="hidden" name="wizdebug" value="1">
	</FORM>

	<?php
	print_stats_bottom(); exit();
}


# ok, valid user in $u
print "Welcome, $u!";


?>
<?php print_stats_bottom(); ?>

