<?php

require_once "includes.php";


print_stats_top();

print "<H2>Payout Queue</H2><BR>";

	// Try Cache First
	if($payout = apc_fetch('payout')) {
	} else {
	 $payout = file("/var/lib/eligius/7/payout_queue.txt", FILE_IGNORE_NEW_LINES); 
	 // Store Cache for 10 minutes
	 apc_add('payout', $payout, 600);
	}
	// Try Cache First
	if($balance = apc_fetch('balance')) {
	} else {
	 $balance = file_get_contents("/var/lib/eligius/7/balances.json");
	 $balance = json_decode($balance, true);
	 // Store Cache for 10 minutes
	 apc_add('balance', $balance, 600);
	}

	$qt = "<TABLE BORDER=1 id=\"blocklisttable\" CLASS=\"blocklist\">";
	$qt .= "<THEAD><TR><TH>Number</TH><TH>Address</TH><TH>Age</TH><TH>Balance</TH></TR></THEAD>";
	$total = 0; $alltotal = 0; $blocks = 1;
	$oddeven = 1; $oe = " class=\"blockconfirmed\"";
	$paynum = 1;
	foreach($payout as $key) {
		$value = $balance[$key];
		while($value['balance'] > 0) {
			if ($total+$value['balance'] > 2500000000) {
				$maxbal = 2500000000 - $total;
				$qt .= "<TR $oe> <TD>$paynum</TD><TD><A HREF=\"/~wizkid057/newstats/userstats.php/$key\" id=\"$key\">$key</A></TD> <TD>".prettyDuration(time()-$value['oldest'])."</TD> <TD>".prettySatoshis($maxbal)."</TD> </TR>"; 
				if ($oddeven) { $oe = " class=\"oddblockconfirmed\""; $oddeven = 0; } else { $oe = " class=\"blockconfirmed\""; $oddeven = 1; }
				$qt .= "<TR $oe><TD></TD><TD><B>--- BLOCK BOUNDARY---</B></TD> <TD></TD> <TD></TD></TR>";
				if ($oddeven) { $oe = " class=\"oddblockconfirmed\""; $oddeven = 0; } else { $oe = " class=\"blockconfirmed\""; $oddeven = 1; }
				$value['balance'] -= $maxbal;
				$alltotal += $maxbal;
				$total = 0; $blocks++;
				$paynum++;
			} else {
				$qt .= "<TR $oe><TD>$paynum</TD><TD><A HREF=\"/~wizkid057/newstats/userstats.php/$key\" id =\"$key\">$key</A></TD> <TD>".prettyDuration(time()-$value['oldest'])."</TD><TD>".prettySatoshis($value['balance'])."</TD></TR>";
				if ($oddeven) { $oe = " class=\"oddblockconfirmed\""; $oddeven = 0; } else { $oe = " class=\"blockconfirmed\""; $oddeven = 1; }
				$total+=$value['balance'];
				$alltotal+=$value['balance'];
				$value['balance'] = 0;
				$paynum++;
			}
		}
	}
	$qt .= "</TABLE>";
	print "Total: ".prettySatoshis($alltotal)."<BR>";
	print "Block Count: $blocks<BR><BR>";
	print $qt;


print_stats_bottom();
