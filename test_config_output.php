<?php ob_start(); require_once "config.php"; $buf=ob_get_clean(); echo strlen($buf)>0 ? "config outputs ".strlen($buf)." bytes" : "no output from config"; ?>
