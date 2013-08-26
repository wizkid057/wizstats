#!/usr/bin/php
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

if( isLocked() ) die( "Already running.\n" );

$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );

# All the work for this is done by postgresql, which is nice, under this query
$sql = "INSERT INTO $psqlschema.stats_shareagg 
(server, time, user_id, accepted_shares, rejected_shares, blocks_found, hashrate) 
select server, 
to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) AS ttime, 
user_id, 
0+SUM(((our_result::integer) * pow(2,(targetmask-32)))) as acceptedshares, 
COUNT(*)-SUM(our_result::integer) as rejectedshares, 
SUM(upstream_result::integer) as blocksfound, 
((SUM(((our_result::integer) * pow(2,(targetmask-32)))) * POW(2, 32)) / 675) AS hashrate 
from public.shares where time > to_timestamp(((date_part('epoch', (select time from $psqlschema.stats_shareagg order by time desc limit 1))::integer / 675::integer) * 675::integer)+675::integer) and to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) < to_timestamp((date_part('epoch', NOW())::integer / 675::integer) * 675::integer) and server=$serverid group by ttime, server, user_id;";
$result = pg_exec($link, $sql);

unlink( LOCK_FILE );


?>
