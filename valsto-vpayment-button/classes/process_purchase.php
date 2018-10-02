<?php
try {
    
    $scriptPath = dirname(__FILE__);
    $path       = realpath($scriptPath . '/./');
    $filepath   = explode("wp-content", $path);
    define('WP_USE_THEMES', false);
    require ('' . $filepath[0] . '/wp-blog-header.php');
    
    if (! defined('ABSPATH')) {
        exit();
    }
    
    set_error_handler("valsto_proxy_error_handler");
    
    if (! session_id()) {
        session_start();
    }
    
    if (isset($_GET['valsto_transaction_id'])) {
        $_SESSION['valsto_transaction_id'] = $_GET['valsto_transaction_id'];
    } else {
        $_SESSION['current_order'] = $_POST;
    }
    
    header('HTTP/1.0 200 Success', true, 200);
} catch (ErrorException $e) {
    if (isset($settings) && $settings->debug) {
        echo "<b>Error:</b>" . $e->getMessage();
    }
}

restore_error_handler();

// FUNCTIONS

/**
 * 
 * @param string $errno
 * @param string $errstr
 * @param string $errfile
 * @param string $errline
 * @param array $errcontext
 * @throws ErrorException
 */
function valsto_proxy_error_handler($errno, $errstr, $errfile, $errline, array $errcontext)
{
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
