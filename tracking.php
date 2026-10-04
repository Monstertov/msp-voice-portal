<?php

$visitor_tracking = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/framework/request.php';
if (PHP_SAPI !== 'cli' && is_file($visitor_tracking)) {
    require_once $visitor_tracking;
    track_request(basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'process.php');
}
