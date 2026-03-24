<?php
session_start();
session_unset();  // Ø­Ø°Ù Ø¬Ù…ÙŠØ¹ Ù…ØªØºÙŠØ±Ø§Øª Ø§Ù„Ù€ session
session_destroy(); // ØªØ¯Ù…ÙŠØ± Ø§Ù„Ø¬Ù„Ø³Ø© Ø¨Ø§Ù„ÙƒØ§Ù…Ù„

// Ø¥Ø¹Ø§Ø¯Ø© Ø§Ù„ØªÙˆØ¬ÙŠÙ‡ Ù„ØµÙØ­Ø© ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯Ø®ÙˆÙ„
header("Location: login.php");
exit();
?>
