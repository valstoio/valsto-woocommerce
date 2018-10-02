<?php
/**
 * Plugin Name: Valsto Payment for WooCommerce
 * Plugin URI: https://www.wordpress.org/plugins/valsto-payment-for-woocommerce/
 * Description: Easily accept Valsto payments on your WordPress / WooCommerce website.
 * Author: Valsto Inc
 * Author URI: https://www.valsto.com/
 * Version: 1.0.0
 *
 * License
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Required minimums
 */
define( 'WC_VALSTO_VBUTTON_MIN_PHP_VER', '5.4.0' );

class WC_Valsto_VButton_Loader
{
    
    const PLUGIN_NAME_SPACE ="woocommerce-valsto-vpayment";
    
    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;
    
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone() {}
    
    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup() {}
    
    /** @var whether or not we need to load code for / support subscriptions */
    private $subscription_support_enabled = false;
    
    /**
     * Notices (array)
     * @var array
     */
    public $notices = array();
    
    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
        add_action( 'admin_init', array( $this, 'check_environment' ) );
        
        
        // admin_notices is prioritized later to allow concrete classes to use admin_notices to push entries to the notices array
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        
        // Don't hook anything else in the plugin if we're in an incompatible environment
        if ( self::get_environment_warning() ) {
            return;
        }
        
        add_action( 'plugins_loaded', array( $this, 'init_gateways' ), 0 );
        
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
        add_action( 'woocommerce_available_payment_gateways', array( $this, 'possibly_disable_other_gateways' ) );
    }
    
    /**
     * Allow this class and other classes to add slug keyed notices (to avoid duplication)
     */
    public function add_admin_notice( $slug, $class, $message ) {
        $this->notices[ $slug ] = array(
            'class' => $class,
            'message' => $message
        );
    }
    
    /**
     * The primary sanity check, automatically disable the plugin on activation if it doesn't
     * meet minimum requirements.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     */
    public static function activation_check() {
        $environment_warning = self::get_environment_warning( true );
        if ( $environment_warning ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( $environment_warning );
        }
    }
    
    /**
     * The backup sanity check, in case the plugin is activated in a weird way,
     * or the environment changes after activation.
     */
    public function check_environment() {
        $environment_warning = self::get_environment_warning();
        
        if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            $this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
        
        $settings = (object) get_option('woocommerce_valsto_payment_settings',array());
        if ( (empty( $settings->merchant_account_pk_valsto ) || empty( $settings->merchant_account_private_pk_valsto )) && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
            $setting_link = $this->get_setting_link();
            
            $this->add_admin_notice( 'prompt_connect', 'notice notice-warning', __( 'The WooCommerce Valsto VPayment is almost ready. To get started, <a href="' . $setting_link . '">configure your Valsto Merchant account</a>.', self::PLUGIN_NAME_SPACE) );
        }
    }
    
    /**
     * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
     * found or false if the environment has no problems.
     */
    static function get_environment_warning( $during_activation = false )
    {
        
        if ( version_compare( phpversion(), WC_VALSTO_VBUTTON_MIN_PHP_VER, '<' ) ) {
            if ( $during_activation ) {
                $message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::PLUGIN_NAME_SPACE, self::PLUGIN_NAME_SPACE );
            } else {
                $message = __( 'The WooCommerce Valsto VPayment plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::PLUGIN_NAME_SPACE );
            }
            return sprintf( $message, WC_VALSTO_VBUTTON_MIN_PHP_VER, phpversion() );
        }
        
        if ( ! function_exists( 'curl_init' ) ) {
            if ( $during_activation ) {
                return __( 'The plugin could not be activated. cURL is not installed.', self::PLUGIN_NAME_SPACE );
            }
            
            return __( 'The WooCommerce Valsto VPayment plugin has been deactivated. cURL is not installed.', self::PLUGIN_NAME_SPACE);
        }
        
        return false;
    }
    
    
    /**
     * Adds plugin action links
     *
     * @since 1.0.0
     */
    public function plugin_action_links( $links )
    {
        $setting_link = $this->get_setting_link();
        
        $plugin_links = array(
            '<a href="' . $setting_link . '">' . __( 'Settings', self::PLUGIN_NAME_SPACE ) . '</a>',
            '<a href="http://docs.woothemes.com/document/valsto-payment/">' . __( 'Docs', self::PLUGIN_NAME_SPACE ) . '</a>',
            '<a href="http://support.woothemes.com/">' . __( 'Support', self::PLUGIN_NAME_SPACE ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }
    
    /**
     * Get setting link.
     *
     * @return string Valsto checkout setting link
     */
    public function get_setting_link()
    {
        return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=valsto_payment');
    }
    
    /**
     * Display any notices we've collected thus far (e.g. for connection, disconnection)
     */
    public function admin_notices()
    {
        foreach ( (array) $this->notices as $notice_key => $notice ) {
            echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
            echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
            echo "</p></div>";
        }
    }
    
    /**
     * Initialize the gateway. Called very early - in the context of the plugins_loaded action
     *
     * @since 1.0.0
     */
    public function init_gateways()
    {
        require_once( plugin_basename( 'classes/wc_gateway_valsto.php' ) );
        require_once( plugin_basename( 'classes/wc_gatefay_valsto_payment_button.php' ) );
        
        load_plugin_textdomain( self::PLUGIN_NAME_SPACE , false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
    }
    
    
    /**
     * Add the gateways to WooCommerce
     *
     * @since 1.0.0
     */
    public function add_gateways( $methods )
    {
        $methods[] = 'WC_Gateway_Valsto_Payment_Button';
        
        return $methods;
    }
    
    /**
     * Returns true if our gateways are enabled, false otherwise
     *
     * @since 1.0.0
     */
    public function are_our_gateways_enabled()
    {
        $gateway_settings = get_option( 'woocommerce_valsto_payment_settings', array() );
        
        if ( empty( $gateway_settings ) ) {
            return false;
        }
        
        return ( "yes" === $gateway_settings['enabled'] );
        
    }
    
    
    /**
     * When cart based Checkout with Valsto is in effect, disable other gateways on checkout
     *
     * @since 1.0.0
     * @param array $gateways
     * @return array
     */
    public function possibly_disable_other_gateways( $gateways )
    {
        
        if ( WC_Valsto_VButton_Loader::getInstance()->does_session_have_postback_data() ) {
            foreach ( $gateways as $id => $gateway ) {
                if ( $id !== 'valsto_payment' ) {
                    unset( $gateways[ $id ] );
                }
            }
        }
        
        return $gateways;
    }
    
    /**
     * Check if postback data is present
     *
     * @since 1.0.0
     * @return bool
     */
    public function does_session_have_postback_data()
    {
        return isset( WC()->session->valsto_payment );
    }
    
    /**
     * Returns form fields common to all the gateways this extension supports
     *
     * @since 1.0.0
     */
    public function get_shared_form_fields ()
    {
        
        return array(
            'enabled' => array(
                'title'       => __( 'Enable Valsto Payment', self::PLUGIN_NAME_SPACE ),
                'label'       => '',
                'type'        => 'checkbox',
                'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', self::PLUGIN_NAME_SPACE ),
                'default'     => 'false',
                'desc_tip'    => true
            ),
            'title_valsto'    => array(
                'title'       => __( 'Valsto Payment Title', self::PLUGIN_NAME_SPACE ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout for Valsto.',self::PLUGIN_NAME_SPACE ),
                'default'     => 'Valsto Payment',
                'desc_tip'    => true
            ),
            'description_valsto' => array(
                'title'          => __( 'Valsto Payment Description', self::PLUGIN_NAME_SPACE ),
                'type'           => 'text',
                'description'    => __( 'This controls the description which the user sees during checkout for Valsto.', self::PLUGIN_NAME_SPACE ),
                'default'        => 'The secure way to pay less.',
                'desc_tip'       => true
            ),
            'merchant_account_valsto' => array(
                'title'               => __( 'Merchant Account Username', self::PLUGIN_NAME_SPACE ),
                'type'                => 'email',
                'description'         => __( 'This is the business username (email) used to log in to the Valsto merchant master account.', self::PLUGIN_NAME_SPACE ),
                'default'             => '',
                'desc_tip'            => true
            ),
            'merchant_account_pk_valsto' => array(
                'title'              => __( 'Merchant Account Public Key', self::PLUGIN_NAME_SPACE ),
                'type'               => 'password',
                'description'        => __( 'This controls the Merchant Account Public Key checkout for Valsto.', self::PLUGIN_NAME_SPACE ),
                'default'            => '',
                'desc_tip'           => true
            ),
            'merchant_account_private_pk_valsto' => array(
                'title'                      => __( 'Merchant Account Private Key', self::PLUGIN_NAME_SPACE ),
                'type'                       => 'password',
                'description'                => __( 'This controls the Merchant Account Private Key checkout for Valsto.', self::PLUGIN_NAME_SPACE ),
                'default'                    => '',
                'desc_tip'                   => true
            ),
            'debug' => array(
                'title'       => __( 'Debug', self::PLUGIN_NAME_SPACE ),
                'label'       => __( 'Enable debugging messages', self::PLUGIN_NAME_SPACE ),
                'type'        => 'checkbox',
                'description' => __( 'Sends debug messages to the WooCommerce System Status log.', self::PLUGIN_NAME_SPACE ),
                'default'     => 'yes'
            ),
            'test_mode_valsto' => array(
                'title'        => __( 'Test mode (sandbox)', self::PLUGIN_NAME_SPACE ),
                'label'        => __( 'Enable Sandbox', self::PLUGIN_NAME_SPACE ),
                'type'         => 'checkbox',
                'description'  => __( 'Enable test mode payments.', self::PLUGIN_NAME_SPACE ),
                'default'      => 'yes'
            ),
            'js_resource_valsto' => array(
                'title'          => __( 'Valsto Payment javascript resource', self::PLUGIN_NAME_SPACE ),
                'type'           => 'url',
                'description'    => __( 'Default value: ', self::PLUGIN_NAME_SPACE ) . $this->get_default_js_resource(),
                'default'        => $this->get_default_js_resource(),
            ),
            'http_proxy'       => array(
                'title'       => __( 'HTTP Proxy', self::PLUGIN_NAME_SPACE ),
                'label'       => __( 'Enable HTTP Proxy.', self::PLUGIN_NAME_SPACE ),
                'type'        => 'checkbox',
                'default'     => 'yes'
            ),
        );
        
    }
    
    /**
     * Returns the default js resource
     */
    public function get_default_js_resource()
    {
        return plugin_dir_url(__FILE__) . 'assets/vpayment-button.js';
    }
    
    /**
     *
     * @since 1.0.0
     */
    public function log( $context, $message )
    {
        if ( empty( $this->log ) ) {
            $this->log = new WC_Logger();
        }
        
        $this->log->add( 'woocommerce-valsto-payment', $context . " - " . $message );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $context . " - " . $message );
        }
    }
    
    /**
     * Return the plugin's about URL.
     * @return string
     */
    public static function get_about_url()
    {
        return "https://www.valsto.com/";
    }
    
    /**
     * Return the login URL into Valsto Platform.
     * @return string
     */
    public static function get_login_url()
    {
        return "https://www.valsto.com/login";
    }
}

$GLOBALS['wc_valsto_vbutton_loader'] = WC_Valsto_VButton_Loader::getInstance();
register_activation_hook( __FILE__, array( 'WC_Valsto_VButton_Loader', 'activation_check' ));
