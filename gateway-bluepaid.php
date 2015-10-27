<?php
/*
	Plugin Name: WooCommerce bluepaid Gateway
	Plugin URI: http://addons.dev2com.fr/produit/module-bluepaid-pour-wordpress-et-woocommerce/
	Description: Une plateforme de paiement sécurisée pour l'encaissement de vos cartes bancaires, bluepaid.
	Version: 1.2.1
	Author: dev2com - JL
	Author URI: http://www.dev2com.fr
	Requires at least: 3.5
	Tested up to: 4.3.1
 	Text Domain: woocommerce-gateway-bluepaid
	Domain Path: /i18n/
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

add_action( 'plugins_loaded', 'woocommerce_bluepaid_init', 0 );

load_plugin_textdomain( 'wc_bluepaid', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_bluepaid_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/bluepaid.class.php' ) );
	//require_once( plugin_basename( 'bluepaid.class.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_bluepaid_add_gateway' );

} // End woocommerce_bluepaid_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_bluepaid_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_bluepaid';
	return $methods;
} // End woocommerce_bluepaid_add_gateway()


/*
 *
 * Settings link
 *
 */
function woocommerce_bluepaid_settings_action_links( $links, $file ) {
    array_push( $links, $mylink );     
    // link to settings
    array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_bluepaid' ) . '">' . __( 'Settings' ) . '</a>' ); 
    return $links;
}
add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), 'woocommerce_bluepaid_settings_action_links', 10, 2 );


/**
 * Load plugin textdomain.
 *
 * @since 1.0.0
 */
function wc_bluepaid_load_textdomain() {
  load_plugin_textdomain( 'woocommerce-gateway-bluepaid', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n' ); 
}
add_action( 'plugins_loaded', 'wc_bluepaid_load_textdomain' );