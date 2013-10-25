<?php


require_once 'includes.php';


if (isset($_GET["poolhashrate"])) {

        if($cppsrbjsondec = apc_fetch('cppsrb_json')) {
        } else {
                $cppsrbjson = file_get_contents("/var/lib/eligius/$serverid/cppsrb.json");
                $cppsrbjsondec = json_decode($cppsrbjson, true);
                apc_store('cppsrb_json', $cppsrbjsondec, 60);
        }
        
        $globalcppsrb = $cppsrbjsondec[''];
        
        $my_shares = $globalcppsrb["shares"];
        
        print sprintf( "%s ( { \"poolhashrate\" : \"%F\" } )", $_GET['callback'], $my_shares[256]*4294967296/256 );
        
}
?>
