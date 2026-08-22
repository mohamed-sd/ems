<?php
/* يدسُّ ملفَّ شاشةٍ فيه خريطةُ دورٍ ⇐ نطاق، ثم يقيس، ثم يكنس. */
$f = 'C:/wamp64/www/ems/Finance/_negbelt_scope.php';
file_put_contents($f, "<?php\nif (\$user_role == '4') { \$party_type = 'employee'; }\n");
$o=array(); exec('"C:/wamp64/bin/php/php8.2.30/php.exe" C:/wamp64/www/ems/tests/injfrd01_sec001_party_scope_failclosed.php 2>&1',$o,$rc);
@unlink($f);
$caught = ($rc !== 0);
printf("  %s خريطةُ دورٍ مدسوسةٌ في شاشةٍ **تُمسَك**\n", $caught?'✔':'✘');
exit($caught?0:1);
