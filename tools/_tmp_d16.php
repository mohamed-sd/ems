<?php
require_once __DIR__ . '/../config.php';
$c = $conn->query("SHOW COLUMNS FROM repair01_w13_decisions"); $f=array();
while($y=$c->fetch_assoc()){$f[]=$y['Field'];}
$sel = in_array('answer',$f) ? 'answer' : 'ruling';
$r = $conn->query("SELECT decision_id, question, $sel a, rationale, src_ref FROM repair01_w13_decisions
                   WHERE question LIKE '%تحقيق%' OR $sel LIKE '%DEC-OPEN-16%' OR rationale LIKE '%DEC-OPEN-16%'
                      OR src_ref LIKE '%16%' ORDER BY decision_id");
if(!$r){echo "ERR ".$conn->error."\n"; exit;}
$n=0; while($x=$r->fetch_assoc()){ $n++;
  echo "  {$x['decision_id']}\n   س: {$x['question']}\n   ج: {$x['a']}\n   علّة: {$x['rationale']}\n   مرجع: {$x['src_ref']}\n\n"; }
if(!$n) echo "  (لا صفَّ يذكره)\n";
