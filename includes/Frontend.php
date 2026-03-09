<?php
/**
 * WC Installment Plans Frontend Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Installment_Plans_Frontend {
	
	const POST_TYPE = 'wc_installment_plan';
	
	private static $instance = null;
	
	private $months = [
		'3'  => '3 месяца',
		'6'  => '6 месяцев',
		'9'  => '9 месяцев',
		'12' => '12 месяцев',
		'18' => '18 месяцев',
		'24' => '24 месяца',
	];
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	public function __construct() {
		// Добавление блока рассрочки на странице товара
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'display_installment_block' ], 25 );
		
		// Загрузка стилей и скриптов
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// AJAX обработчик для получения данных
		add_action( 'wp_ajax_nopriv_wc_installment_calculate', [ $this, 'ajax_calculate_installment' ] );
		add_action( 'wp_ajax_wc_installment_calculate', [ $this, 'ajax_calculate_installment' ] );
	}
	
	/**
	 * Отображение блока рассрочки
	 */
	public function display_installment_block() {
		global $product;
		
		if ( ! $product ) {
			return;
		}
		
		$product_id = $product->get_id();
		$is_enabled = get_post_meta( $product_id, '_wc_installment_enabled', true );
		
		if ( ! $is_enabled ) {
			return;
		}
		
		$installment_plans = get_post_meta( $product_id, '_wc_installment_plans', true );
		$installment_months = get_post_meta( $product_id, '_wc_installment_months', true );
		
		if ( empty( $installment_plans ) ) {
			return;
		}
		
		$product_price = $product->get_price();
		if ( ! $product_price ) {
			return;
		}
		
		?>
		<div class="wc-installment-block" style="margin: 30px 0; padding: 20px; background-color: #f9f9f9; border-radius: 8px; border: 1px solid #e0e0e0;">
			<h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #333;">
				<?php echo esc_html__( 'Варианты рассрочки', 'wc-installment-plans' ); ?>
			</h3>
			
			<!-- Вкладки месяцев -->
			<div class="installment-months-tabs" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
				<?php
				$first_month = true;
				foreach ( $this->months as $month_value => $month_label ) :
					// Проверяем, используется ли этот месяц в каком-нибудь плане
					$month_used = false;
					foreach ( $installment_plans as $plan_id ) {
						$months_key = $plan_id . '_months';
						$plan_months = isset( $installment_months[ $months_key ] ) ? $installment_months[ $months_key ] : [];
						if ( in_array( $month_value, $plan_months, true ) ) {
							$month_used = true;
							break;
						}
					}
					
					if ( ! $month_used ) {
						continue;
					}
					
					$is_active = $first_month ? 'active' : '';
					$first_month = false;
					?>
					<button 
						class="installment-month-tab <?php echo esc_attr( $is_active ); ?>" 
						data-month="<?php echo esc_attr( $month_value ); ?>"
						style="padding: 10px 20px; border: 2px solid #ddd; background-color: #e8f5e9; border-radius: 20px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s; color: #333;"
					>
						<?php echo esc_html( $month_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			
			<!-- Список планов рассрочки -->
			<div class="installment-plans-list">
				<?php
				$first_month = true;
				foreach ( $this->months as $month_value => $month_label ) :
					// Проверяем, используется ли этот месяц в каком-нибудь плане
					$month_plans = [];
					foreach ( $installment_plans as $plan_id ) {
						$months_key = $plan_id . '_months';
						$plan_months = isset( $installment_months[ $months_key ] ) ? $installment_months[ $months_key ] : [];
						if ( in_array( $month_value, $plan_months, true ) ) {
							$month_plans[] = $plan_id;
						}
					}
					
					if ( empty( $month_plans ) ) {
						continue;
					}
					
					$is_active = $first_month ? 'active' : '';
					$display = $first_month ? 'block' : 'none';
					$first_month = false;
					?>
					<div class="installment-month-content <?php echo esc_attr( $is_active ); ?>" data-month="<?php echo esc_attr( $month_value ); ?>" style="display: <?php echo esc_attr( $display ); ?>;">
						<?php foreach ( $month_plans as $plan_id ) : 
							$plan = get_post( $plan_id );
							$logo = get_the_post_thumbnail_url( $plan_id, 'thumbnail' );
							$commissions = get_post_meta( $plan_id, '_wc_installment_commissions', true );
							$commission = isset( $commissions[ $month_value ] ) ? floatval( $commissions[ $month_value ] ) : 0;
							
							// Расчет ежемесячного платежа
							$total_with_commission = $product_price + ( $product_price * $commission / 100 );
							$monthly_payment = $total_with_commission / intval( $month_value );
							?>
							<div class="installment-plan-item" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #ddd;">
								<?php if ( $logo ) : ?>
									<div style="flex-shrink: 0;">
										<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $plan->post_title ); ?>" style="max-height: 50px; width: auto;">
									</div>
								<?php endif; ?>
								
								<div style="flex: 1;">
									<div style="font-weight: 600; color: #333; margin-bottom: 5px;">
										<?php echo esc_html( $plan->post_title ); ?>
									</div>
									<div style="font-size: 14px; color: #666;">
										<?php 
										echo wp_kses_post(
											sprintf(
												'<strong>%s</strong> / месяц',
												wc_price( $monthly_payment )
											)
										);
										?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Загрузка стилей и скриптов
	 */
	public function enqueue_assets() {
		if ( is_product() ) {
			wp_enqueue_style( 'wc-installment-plans-frontend', WC_INSTALLMENT_PLANS_URL . 'assets/frontend.css', [], WC_INSTALLMENT_PLANS_VERSION );
			wp_enqueue_script( 'wc-installment-plans-frontend', WC_INSTALLMENT_PLANS_URL . 'assets/frontend.js', [ 'jquery' ], WC_INSTALLMENT_PLANS_VERSION, true );
			
			wp_localize_script( 'wc-installment-plans-frontend', 'wcInstallmentPlans', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			] );
		}
	}
	
	/**
	 * AJAX обработчик расчета рассрочки
	 */
	public function ajax_calculate_installment() {
		check_ajax_referer( 'wc_installment_nonce', 'nonce' );
		
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : '12';
		
		if ( ! $product_id ) {
			wp_send_json_error( __( 'Invalid product', 'wc-installment-plans' ) );
		}
		
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( __( 'Product not found', 'wc-installment-plans' ) );
		}
		
		wp_send_json_success( [
			'product_id' => $product_id,
			'month'      => $month,
		] );
	}
}