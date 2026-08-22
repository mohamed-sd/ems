<?php
/* حزامٌ سلبيّ: أترسُب سقّاطةُ البذرةِ على نقصٍ حقيقيّ؟ ولا تُترك القاعدةُ مَمسوسة. */
error_reporting(E_ALL & ~E_DEPRECATED); mb_internal_encoding('UTF-8');
$c = new mysqli('127.0.0.1','root','','ems_release_probe',3307); $c->set_charset('utf8mb4');
$base = json_decode(file_get_contents('C:/wamp64/www/ems/docs/install_seed_baseline.json'), true);
$run = function(){ $o=array(); exec('"C:/wamp64/bin/php/php8.2.30/php.exe" C:/wamp64/www/ems/tests/install_proof.php --host=localhost:3307 --db=ems_release_probe --user=release_probe --pass=Probe-Throwaway-9f2c1a 2>&1',$o,$rc); return $rc; };
printf("  %s الحيُّ يمرّ\n", $run()===0?'✔':'✘');
$c->query("CREATE TEMPORARY TABLE _bk AS SELECT * FROM equipments_types");
$c->query("DELETE FROM equipments_types ORDER BY id DESC LIMIT 3");
$rc = $run();
printf("  %s ونقصُ البذرةِ يُمسَك (حُذفت 3 من equipments_types)\n", $rc!==0?'✔':'✘');
$c->query("INSERT INTO equipments_types SELECT * FROM _bk WHERE id NOT IN (SELECT id FROM equipments_types)");
$back = $run();
printf("  %s وأُعيد ما نُزع — الشاهدُ أخضرُ ثانيةً\n", $back===0?'✔':'✘');
exit(($rc!==0 && $back===0)?0:1);
