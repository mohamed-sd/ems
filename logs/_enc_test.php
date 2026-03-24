<?php
$s = "ØÙØÙÙØØ";
echo "orig=$s\n";
echo "f1=" . utf8_encode(utf8_decode($s)) . "\n";
echo "f2=" . mb_convert_encoding($s, 'UTF-8', 'Windows-1252') . "\n";
echo "f3=" . iconv('UTF-8', 'ISO-8859-1//IGNORE', $s) . "\n";
$t = iconv('UTF-8', 'ISO-8859-1//IGNORE', $s);
echo "f4=" . mb_convert_encoding($t, 'UTF-8', 'UTF-8') . "\n";
