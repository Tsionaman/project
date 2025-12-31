<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
?>