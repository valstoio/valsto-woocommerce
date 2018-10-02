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
    
    if (! isset($_GET['vTID']) || ! isset($_GET['vTP'])) {
        wp_redirect(get_home_url());
        exit();
    }
    
    $tId          = $_GET['vTID']; // Transaction ID
    $proccess     = $_GET['vTP']; // Proccess
    $valstoDomain = "https://staging.api.valsto.com/";
    
    $settings = (object) get_option('woocommerce_valsto_payment_settings', array());
    
    $url = sprintf($valstoDomain . '/api/transactions/%s/%s/detail/%s', $settings->merchant_account_valsto, $settings->merchant_account_pk_valsto, // <- PUBLIC KEY
    $tId);
    
    $opts = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode([
                'privateApiKey' => $settings->merchant_account_private_pk_valsto
            ])
        )
    );
    
    $context = stream_context_create($opts);
    
    if (isset($settings) && $settings->debug) {
        echo sprintf("IPN URL: %s <br/>", $url);
    }
    
    $response = file_get_contents($url, null, $context);
    
    $transaction = json_decode($response);
    
    if ($transaction != null) {
        
        switch ($proccess) {
            case 'created':
                if (isset($_SESSION['current_order'])) {
                    $status = [
                        'INITIATED',
                        'PUSH_NOTIFIED',
                        'ON_ADDRESS_VERIFICATION'
                    ];
                    if (in_array($transaction->currentStatus, $status)) {
                        $_GET['vDiscount'] = $transaction->vdiscount;
                        $_GET['vDiscountAmount'] = $transaction->vdiscountAmount;
                        $_GET['taxes'] = $transaction->taxes;
                        $order = get_order($tId);
                        if (! $order || $order == null) {
                            $_POST = $_SESSION['current_order'];
                            $woocommerce = WC();
                            $checkout = $woocommerce->checkout();
                            $checkout->process_checkout();
                        }
                    }
                }
                
                break;
            
            case 'updated':
                $status = [
                    'INITIATED' => 'wc-pending',
                    'PUSH_NOTIFIED' => 'wc-processing',
                    'PHONE_CHECKOUT' => 'wc-processing',
                    'COMPLETE_REJECTED' => 'wc-cancelled',
                    'COMPLETE_APPROVED' => 'wc-completed',
                    'INCOMPLETE_TIMEOUT' => 'wc-failed',
                    'COMPLETE_PROCESSED' => 'wc-completed',
                    'COMPLETE_CANCELED' => 'wc-cancelled',
                    'COMPLETE_RETURNED' => 'wc-refunded',
                    'ON_ADDRESS_VERIFICATION' => 'wc-processing'
                ];
                
                if (isset($status[$transaction->currentStatus])) {
                    $newStatus = $status[$transaction->currentStatus];
                    $order = get_order($tId);
                    
                    if ($order && $order->post_status != $newStatus) {
                        $order->update_status($newStatus, sprintf('Valsto current status: %s', $transaction->currentStatusAlias));
                    }
                }
                break;
            
            default:
                break;
        }
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

/**
 * Get the order by Id.
 * @param integer $tId
 * @return WS_Or
 */
function get_order($tId)
{
    $order = null;
    
    $posts = get_posts([
        'meta_key' => '_valsto_transaction_id',
        'meta_value' => $tId,
        'post_type' => 'shop_order'
    ]);
    
    if (isset($posts[0])) {
        $post = $posts[0];
        $order = wc_get_order((int) $post->ID);
    }
    
    return $order;
}
