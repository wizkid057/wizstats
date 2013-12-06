#!/usr/bin/php
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

if( isLocked() ) die( "Already running.\n" );

$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );


$sql = "select to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) as lst from public.shares where server=$serverid order by id desc limit 1";
$result = pg_exec($link, $sql);
$row = pg_fetch_array($result, 0);
$latestsharetime = $row["lst"];

$sql = "select to_timestamp(((date_part('epoch', (select time from wizkid057.stats_shareagg order by time desc limit 1))::integer / 675::integer) * 675::integer)+675::integer) as fst";
$result = pg_exec($link, $sql);
$row = pg_fetch_array($result, 0);
$firstsharetime = $row["fst"];

# All the work for this is done by postgresql, which is nice, under this query
$sql = "INSERT INTO $psqlschema.stats_shareagg (server, time, user_id, accepted_shares, rejected_shares, blocks_found, hashrate)
select server, to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) AS ttime, user_id,
0+SUM(((our_result::integer) * pow(2,(targetmask-32)))) as acceptedshares, COUNT(*)-SUM(our_result::integer) as rejectedshares, SUM(upstream_result::integer) as blocksfound,
((SUM(((our_result::integer) * pow(2,(targetmask-32)))) * 4294967296) / 675) AS hashrate
from public.shares where time > '$firstsharetime' and to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) < '$latestsharetime' and server=$serverid group by ttime, server, user_id;";
$result = pg_exec($link, $sql);

unlink( LOCK_FILE );


?>
