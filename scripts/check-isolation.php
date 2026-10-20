<?php
$socket = @fsockopen('1.1.1.1', 443, $error, $message, 2);
if ($socket) {
    fclose($socket);
    fwrite(STDERR, "FAIL: application container can reach an external service\n");
    exit(1);
}
echo "PASS: external TCP connection is blocked\n";
