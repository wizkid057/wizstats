<?php


require_once 'includes.php';

print_stats_top();



?>

<div id="graphdiv3" style="width:100%; height:275px;"></div>
<script type="text/javascript">
  g2 = new Dygraph(
    document.getElementById("graphdiv3"),
    "poolhashrategraph.php?extended=1",
        { strokeWidth: 2.25,
        'hashrate': {fillGraph: true },
        labelsDivStyles: { border: '1px solid black' },
        title: '<?php echo $poolname; ?> Hashrate Graph',
        xlabel: 'Date',
        ylabel: 'Gh/sec',
        animatedZooms: true,
        includeZero: true
        }
  );
</script>

<?php
print_stats_bottom();

?>
