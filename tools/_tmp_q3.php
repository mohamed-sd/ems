<?php
require __DIR__.'/../config.php';
$r=$conn->query("SHOW TABLES LIKE '%nav%'"); while($x=$r->fetch_row()){echo $x[0]."\n";}
echo "---\n";
$r=$conn->query("SHOW TABLES LIKE '%canonical%'"); while($x=$r->fetch_row()){echo $x[0]."\n";}
