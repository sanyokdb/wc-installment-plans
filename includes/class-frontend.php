<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Installment_Frontend {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_share', [ $this, 'render_installments' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_update_installment_variation', [ $this, 'ajax_update_installment_variation' ] );
		add_action( 'wp_ajax_nopriv_update_installment_variation', [ $this, 'ajax_update_installment_variation' ] );
	}

	public function render_installments() {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$product_id = $product->get_id();
		$enabled = get_post_meta( $product_id, 'wc_installment_enabled', true );

		if ( ! $enabled ) {
			return;
		}

		$plans = get_post_meta( $product_id, 'wc_installment_plans', true );
		$months = get_post_meta( $product_id, 'wc_installment_months', true );

		if ( empty( $plans ) || empty( $months ) ) {
			return;
		}

		$is_variable = $product->is_type( 'variable' );

		if ( $is_variable ) {
			$min_price = (float) $product->get_variation_price( 'min' );
			$max_price = (float) $product->get_variation_price( 'max' );
			$price     = $min_price;
		} else {
			$price = (float) $product->get_price();
		}

		if ( ! $price ) {
			return;
		}

		// Получаем доступные месяцы
		$available_months = [];
		foreach ( $plans as $plan_id ) {
			foreach ( [ '3', '6', '9', '12', '18', '24' ] as $month ) {
				$key = $plan_id . '_' . $month;
				if ( isset( $months[ $key ] ) && ! in_array( $month, $available_months ) ) {
					$available_months[] = $month;
				}
			}
		}

		if ( empty( $available_months ) ) {
			return;
		}

		usort( $available_months, [ $this, 'sort_months' ] );

		$default_month = $available_months[0];
		$default_plan_id = $plans[0];
		
		// Вычисляем стартовую общую сумму
		$default_commissions = get_post_meta( $default_plan_id, 'wc_installment_commissions', true );
		$default_commission = isset( $default_commissions[ $default_month ] ) ? floatval( $default_commissions[ $default_month ] ) : 0;
		$default_total = $price + ( $price * $default_commission / 100 );
		?>
		<div class="installment-section" id="installment-section-<?php echo esc_attr( $product_id ); ?>" data-price="<?php echo esc_attr( $price ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-default-plan="<?php echo esc_attr( $default_plan_id ); ?>" data-default-month="<?php echo esc_attr( $default_month ); ?>"<?php if ( $is_variable ) : ?> data-is-variable="1" data-min-price="<?php echo esc_attr( $min_price ); ?>" data-max-price="<?php echo esc_attr( $max_price ); ?>"<?php endif; ?>>
			<h3 class="installment-title">Варианты рассрочки</h3>

			<!-- Вкладки месяцев -->
			<div class="installment-months-tabs">
				<?php foreach ( $available_months as $month ) : ?>
					<button class="installment-month-tab <?php echo $month === $default_month ? 'active' : ''; ?>" data-month="<?php echo esc_attr( $month ); ?>">
						<?php echo esc_html( $month ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<!-- Список планов -->
			<div class="installment-plans">
				<?php foreach ( $available_months as $month ) : ?>
					<div class="installment-plans-group <?php echo $month === $default_month ? 'active' : ''; ?>" data-month="<?php echo esc_attr( $month ); ?>">
						<?php 
						$first_in_group = true;
						foreach ( $plans as $plan_id ) : 
							$key = $plan_id . '_' . $month;
							if ( ! isset( $months[ $key ] ) ) {
								continue;
							}

							$plan = get_post( $plan_id );
							if ( ! $plan ) {
								continue;
							}

							$logo_url = get_the_post_thumbnail_url( $plan_id, 'thumbnail' );
							$commissions = get_post_meta( $plan_id, 'wc_installment_commissions', true );
							$commission = isset( $commissions[ $month ] ) ? floatval( $commissions[ $month ] ) : 0;

							$total = $price + ( $price * $commission / 100 );
							$monthly = $total / intval( $month );
							
							$is_active = $first_in_group;
							$first_in_group = false;
						?>
							<div class="installment-plan <?php echo $is_active ? 'active' : ''; ?>"
								data-total="<?php echo esc_attr( $total ); ?>"
								data-monthly="<?php echo esc_attr( $monthly ); ?>"
								data-original-total="<?php echo esc_attr( $total ); ?>"
								data-original-monthly="<?php echo esc_attr( $monthly ); ?>"
								data-plan-id="<?php echo esc_attr( $plan_id ); ?>">
								<div class="installment-plan-left">
									<?php if ( $logo_url ) : ?>
										<div class="installment-plan-logo">
											<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $plan->post_title ); ?>">
										</div>
									<?php endif; ?>
									<div class="installment-plan-name">
										<?php echo esc_html( $plan->post_title ); ?>
									</div>
								</div>
								<div class="installment-plan-price">
									<?php echo wp_kses_post( wc_price( $monthly ) ); ?>/месяц
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Общая сумма -->
			<div class="installment-total">
				<span class="installment-total-label">Общая сумма:</span>
				<span class="installment-total-price">
					<?php echo wp_kses_post( wc_price( $default_total ) ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	private function sort_months( $a, $b ) {
		return (int) $a - (int) $b;
	}

	public function enqueue_assets() {
		if ( is_product() ) {
			wp_enqueue_style( 'installment-frontend', WC_INSTALLMENT_PLANS_URL . 'assets/frontend.css', [], WC_INSTALLMENT_PLANS_VERSION );
			wp_enqueue_script( 'installment-frontend', WC_INSTALLMENT_PLANS_URL . 'assets/frontend.js', [ 'jquery' ], WC_INSTALLMENT_PLANS_VERSION, true );
			wp_localize_script( 'installment-frontend', 'wcInstallmentAjax', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'update_installment_variation' ),
			] );
		}
	}

	public function ajax_update_installment_variation() {
		check_ajax_referer( 'update_installment_variation', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$price      = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;

		if ( ! $product_id || $price <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid data' ] );
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => 'Invalid product' ] );
			return;
		}

		$enabled = get_post_meta( $product_id, 'wc_installment_enabled', true );
		if ( ! $enabled ) {
			wp_send_json_error( [ 'message' => 'Installments not enabled for this product' ] );
			return;
		}

		$plans       = get_post_meta( $product_id, 'wc_installment_plans', true );
		$months_meta = get_post_meta( $product_id, 'wc_installment_months', true );

		if ( empty( $plans ) || empty( $months_meta ) ) {
			wp_send_json_error( [ 'message' => 'No plans found' ] );
			return;
		}

		$available_months = [];
		foreach ( $plans as $plan_id ) {
			foreach ( [ '3', '6', '9', '12', '18', '24' ] as $month ) {
				$key = $plan_id . '_' . $month;
				if ( isset( $months_meta[ $key ] ) && ! in_array( $month, $available_months, true ) ) {
					$available_months[] = $month;
				}
			}
		}

		usort( $available_months, [ $this, 'sort_months' ] );

		$result_months     = [];
		$default_total     = 0;
		$commissions_cache = [];
		$plan_names_cache  = [];

		foreach ( $available_months as $month ) {
			$result_months[ $month ] = [];
			foreach ( $plans as $plan_id ) {
				$key = $plan_id . '_' . $month;
				if ( ! isset( $months_meta[ $key ] ) ) {
					continue;
				}
				if ( ! isset( $commissions_cache[ $plan_id ] ) ) {
					$commissions_cache[ $plan_id ] = get_post_meta( $plan_id, 'wc_installment_commissions', true );
				}
				if ( ! isset( $plan_names_cache[ $plan_id ] ) ) {
					$plan_post = get_post( $plan_id );
					$plan_names_cache[ $plan_id ] = $plan_post ? $plan_post->post_title : '';
				}
				$commissions = $commissions_cache[ $plan_id ];
				$commission  = isset( $commissions[ $month ] ) ? floatval( $commissions[ $month ] ) : 0;
				$total       = $price + ( $price * $commission / 100 );
				$monthly     = $total / intval( $month );

				$result_months[ $month ][] = [
					'plan_id'   => $plan_id,
					'plan_name' => $plan_names_cache[ $plan_id ],
					'total'     => (int) round( $total ),
					'monthly'   => (int) round( $monthly ),
				];
			}
		}

		if ( ! empty( $available_months ) && ! empty( $result_months[ $available_months[0] ] ) ) {
			$default_total = $result_months[ $available_months[0] ][0]['total'];
		}

		wp_send_json_success( [
			'price'         => (int) round( $price ),
			'months'        => $result_months,
			'default_total' => $default_total,
		] );
	}
}