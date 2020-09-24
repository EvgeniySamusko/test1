<?php

if (!isset($_GET['noinit'])) {
    if (file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/include/events.php')) {
        include $_SERVER['DOCUMENT_ROOT'].'/local/php_interface/include/events.php';
    }
}
