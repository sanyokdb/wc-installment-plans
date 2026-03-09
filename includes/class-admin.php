<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Installment_Admin {
	private static $instance = null;
	private $months = [
		'3'  => '3',
		'6'  => '6',
		'9'  => '9',
		'12' => '12',
		'18' => '18',
		'24' => '24',
	];

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Для типа записи планов
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_plan_metabox' ] );
		add_action( 'save_post_wc_installment_plan', [ $this, 'save_plan_metabox' ] );

		// Для товаров
		add_action( 'add_meta_boxes', [ $this, 'add_product_metabox' ] );
		add_action( 'save_post_product', [ $this, 'save_product_metabox' ] );

		// Скрипты и стили
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			'Планы рассрочки',
			'Планы рассрочки',
			'manage_options',
			'edit.php?post_type=wc_installment_plan'
		);
	}

	/**
	 * Метабокс для плана рассрочки
	 */
	public function add_plan_metabox() {
		add_meta_box(
			'wc_installment_plan_meta',
			'Комиссии',
			[ $this, 'render_plan_metabox' ],
			'wc_installment_plan',
			'normal',
			'high'
		);
	}

	public function render_plan_metabox( $post ) {
		wp_nonce_field( 'wc_installment_plan_save', 'wc_installment_plan_nonce' );

		$commissions = get_post_meta( $post->ID, 'wc_installment_commissions', true );
		if ( ! $commissions ) {
			$commissions = [];
		}

		echo '<table class="widefat striped"><thead><tr><th>Срок</th><th>Комиссия (%)</th></tr></thead><tbody>';

		foreach ( $this->months as $month_val => $month_label ) {
			$value = isset( $commissions[ $month_val ] ) ? $commissions[ $month_val ] : '';
			echo '<tr><td><strong>' . esc_html( $month_val ) . ' месяцев</strong></td>';
			echo '<td><input type="number" name="wc_commission[' . esc_attr( $month_val ) . ']" value="' . esc_attr( $value ) . '" step="0.01" min="0" max="100" style="width:100%; padding:8px;"></td></tr>';
		}

		echo '</tbody></table>';
	}

	public function save_plan_metabox( $post_id ) {
		if ( ! isset( $_POST['wc_installment_plan_nonce'] ) || ! wp_verify_nonce( $_POST['wc_installment_plan_nonce'], 'wc_installment_plan_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$commissions = [];
		if ( isset( $_POST['wc_commission'] ) ) {
			foreach ( $_POST['wc_commission'] as $month => $commission ) {
				$commissions[ sanitize_text_field( $month ) ] = floatval( $commission );
			}
		}

		update_post_meta( $post_id, 'wc_installment_commissions', $commissions );
	}

	/**
	 * Метабокс для товара
	 */
	public function add_product_metabox() {
		add_meta_box(
			'wc_installment_product',
			'Варианты рассрочки',
			[ $this, 'render_product_metabox' ],
			'product',
			'normal',
			'high'
		);
	}

	public function render_product_metabox( $post ) {
		wp_nonce_field( 'wc_installment_product_save', 'wc_installment_product_nonce' );

		$enabled = get_post_meta( $post->ID, 'wc_installment_enabled', true );
		$selected_plans = get_post_meta( $post->ID, 'wc_installment_plans', true );
		$selected_months = get_post_meta( $post->ID, 'wc_installment_months', true );

		if ( ! is_array( $selected_plans ) ) {
			$selected_plans = [];
		}
		if ( ! is_array( $selected_months ) ) {
			$selected_months = [];
		}

		// Получаем все планы
		$plans = get_posts( [
			'post_type'      => 'wc_installment_plan',
			'posts_per_page' => -1,
		] );

		// Включение/выключение
		echo '<div style="margin-bottom: 20px;">';
		echo '<label style="display:flex; align-items:center; gap:10px;">';
		echo '<input type="checkbox" name="wc_installment_enabled" value="1" ' . checked( $enabled, 1, false ) . '> ';
		echo '<span style="font-weight:600; font-size:14px;">' . esc_html__( 'Включить варианты рассрочки', 'wc-installment-plans' ) . '</span>';
		echo '</label>';
		echo '</div>';

		// Планы в таблице
		if ( ! empty( $plans ) ) {
			echo '<div id="wc-plans-table-container" style="display:' . ( $enabled ? 'block' : 'none' ) . '; overflow-x:auto;">';
			echo '<table class="wp-list-table widefat striped wc-installment-table" style="margin:0;">';
			echo '<thead style="background-color:#f0f0f0;">';
			echo '<tr>';
			echo '<th style="width:200px; padding:12px; font-weight:600; border:1px solid #ddd;">Месяцы</th>';
			
			foreach ( $this->months as $month_val => $month_label ) {
				echo '<th style="width:100px; padding:12px; text-align:center; font-weight:600; border:1px solid #ddd;">' . esc_html( $month_val ) . '</th>';
			}
			
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';

			foreach ( $plans as $plan ) {
				$plan_id = $plan->ID;
				
				echo '<tr style="background-color:#fff;">';
				echo '<td style="padding:12px; border:1px solid #ddd; font-weight:600;">';
				echo esc_html( $plan->post_title );
				echo '</td>';

				foreach ( $this->months as $month_val => $month_label ) {
					$months_key = $plan_id . '_' . $month_val;
					$is_month_checked = isset( $selected_months[ $months_key ] );
					
					echo '<td style="text-align:center; padding:12px; border:1px solid #ddd;">';
					echo '<input type="checkbox" name="wc_month[' . esc_attr( $months_key ) . ']" value="1" ' . checked( $is_month_checked, true, false ) . ' style="cursor:pointer;">';
					echo '</td>';
				}

				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		} else {
			echo '<div style="padding:20px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; color:#666;">';
			echo esc_html__( 'Сначала создайте планы в WooCommerce > Планы рассрочки', 'wc-installment-plans' );
			echo '</div>';
		}

		// JavaScript для переключения видимости таблицы
		?>
		<script>
		jQuery(document).ready(function($) {
			const $checkbox = $('input[name="wc_installment_enabled"]');
			const $container = $('#wc-plans-table-container');

			$checkbox.on('change', function() {
				if ($(this).is(':checked')) {
					$container.slideDown();
				} else {
					$container.slideUp();
				}
			});
		});
		</script>
		<?php
	}

	public function save_product_metabox( $post_id ) {
		if ( ! isset( $_POST['wc_installment_product_nonce'] ) || ! wp_verify_nonce( $_POST['wc_installment_product_nonce'], 'wc_installment_product_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Сохраняем включение
		$enabled = isset( $_POST['wc_installment_enabled'] ) ? 1 : 0;
		update_post_meta( $post_id, 'wc_installment_enabled', $enabled );

		// Сохраняем планы
		$plans = [];
		if ( isset( $_POST['wc_month'] ) ) {
			// Извлекаем уникальные ID планов из ключей месяцев
			foreach ( $_POST['wc_month'] as $key => $value ) {
				// Ключ формата: plan_id_month
				$parts = explode( '_', $key );
				if ( ! empty( $parts[0] ) ) {
					$plan_id = intval( $parts[0] );
					if ( $plan_id && ! in_array( $plan_id, $plans ) ) {
						$plans[] = $plan_id;
					}
				}
			}
		}
		update_post_meta( $post_id, 'wc_installment_plans', $plans );

		// Сохраняем месяцы
		$months = [];
		if ( isset( $_POST['wc_month'] ) ) {
			foreach ( $_POST['wc_month'] as $key => $value ) {
				$months[ $key ] = 1;
			}
		}
		update_post_meta( $post_id, 'wc_installment_months', $months );
	}

	public function enqueue_admin_assets() {
		wp_enqueue_style( 'wc-installment-admin', WC_INSTALLMENT_PLANS_URL . 'assets/admin.css' );
	}
}