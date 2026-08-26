<?php
require_once __DIR__ . '/../config.php';
echo "══ W02 — حكمُ كلِّ شاشةٍ في السجلّ ══\n";
$r = $conn->query("SELECT w2_why, COUNT(*) n FROM repair01_screen_registry GROUP BY w2_why ORDER BY n DESC LIMIT 12");
while ($x = $r->fetch_assoc()) { printf("  %-52s %s\n", ($x['w2_why'] === '' || $x['w2_why'] === null) ? '(فارغ)' : mb_substr($x['w2_why'],0,50), $x['n']); }
echo "\n══ حكمُ الشبح ══\n";
$r = $conn->query("SELECT ghost_verdict, COUNT(*) n FROM repair01_screen_registry GROUP BY ghost_verdict ORDER BY n DESC");
while ($x = $r->fetch_assoc()) { printf("  %-30s %s\n", ($x['ghost_verdict']==='' ? '(فارغ)' : $x['ghost_verdict']), $x['n']); }
echo "\n══ نوعُ الحارس ══\n";
$r = $conn->query("SELECT guard_kind, COUNT(*) n FROM repair01_screen_registry GROUP BY guard_kind ORDER BY n DESC LIMIT 8");
while ($x = $r->fetch_assoc()) { printf("  %-30s %s\n", ($x['guard_kind']==='' ? '(فارغ)' : $x['guard_kind']), $x['n']); }
echo "\n══ W01 — الملكيّة ══\n";
$c = $conn->query("SHOW COLUMNS FROM repair01_ownership"); $f=array();
while ($y=$c->fetch_assoc()){$f[]=$y['Field'];} echo "  أعمدة: " . implode(', ', $f) . "\n";
echo "  صفوف: " . $conn->query("SELECT COUNT(*) c FROM repair01_ownership")->fetch_assoc()['c'] . "\n";
