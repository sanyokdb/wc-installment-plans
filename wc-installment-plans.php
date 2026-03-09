<?php
/**
 * Plugin Name: WC Installment Plans
 * Plugin URI: https://example.com
 * Description: Плагин для управления вариантами рассрочки в WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wc-installment-plans
 * Domain Path: /languages
 * Requires at least: 6.9.1
 * Requires PHP: 8.0
 * WC requires at least: 10.5.3
 * WC tested up to: 10.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_INSTALLMENT_PLANS_VERSION', '1.0.0' );
define( 'WC_INSTALLMENT_PLANS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_INSTALLMENT_PLANS_URL', plugin_dir_url( __FILE__ ) );

function wc_installment_plans_woocommerce_notice() {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'WC Installment Plans требует WooCommerce для работы.', 'wc-installment-plans' ) . '</p></div>';
}

// Инициализация плагина
function wc_installment_plans_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_installment_plans_woocommerce_notice' );
		return;
	}

	require_once WC_INSTALLMENT_PLANS_PATH . 'includes/class-installation-plan.php';
	require_once WC_INSTALLMENT_PLANS_PATH . 'includes/class-admin.php';
	require_once WC_INSTALLMENT_PLANS_PATH . 'includes/class-frontend.php';

	// Регистрация типа записи
	WC_Installation_Plan::get_instance();

	// Админка
	if ( is_admin() && ! wp_doing_ajax() ) {
		WC_Installment_Admin::get_instance();
	} else {
		WC_Installment_Frontend::get_instance();
	}
}

add_action( 'plugins_loaded', 'wc_installment_plans_init' );

function wc_installment_plans_activate() {
	flush_rewrite_rules();
}

function wc_installment_plans_deactivate() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'wc_installment_plans_activate' );
register_deactivation_hook( __FILE__, 'wc_installment_plans_deactivate' );