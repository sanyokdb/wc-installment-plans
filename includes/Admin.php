<?php
/**
 * WC Installment Plans Admin Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Installment_Plans_Admin {
	
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
		// Регистрация типа записи на init с низким приоритетом
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
		
		// Добавление метабокса
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		
		// Сохранение данных
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_metabox' ], 10, 1 );
		
		// Загрузка стилей и скриптов
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// Кастомизация колонок списка
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'custom_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'custom_column_content' ], 10, 2 );
		
		// Добавление подменю в WooCommerce
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 20 );
		
		// Добавление метабокса в товары
		add_action( 'add_meta_boxes', [ $this, 'add_product_metabox' ] );
		add_action( 'save_post_product', [ $this, 'save_product_metabox' ], 10, 1 );
		
		// Загрузка JS для админки
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_js' ] );
	}
	
	/**
	 * Регистрация типа записи
	 */
	public function register_post_type() {
		$labels = [
			'name'                  => _x( 'Планы рассрочки', 'Post Type General Name', 'wc-installment-plans' ),
			'singular_name'         => _x( 'План рассрочки', 'Post Type Singular Name', 'wc-installment-plans' ),
			'menu_name'             => __( 'Планы рассрочки', 'wc-installment-plans' ),
			'name_admin_bar'        => __( 'План рассрочки', 'wc-installment-plans' ),
			'archives'              => __( 'Архив планов', 'wc-installment-plans' ),
			'attributes'            => __( 'Атрибуты плана', 'wc-installment-plans' ),
			'parent_item_colon'     => __( 'Родительский план:', 'wc-installment-plans' ),
			'all_items'             => __( 'Все планы', 'wc-installment-plans' ),
			'add_new_item'          => __( 'Добавить новый план', 'wc-installment-plans' ),
			'add_new'               => __( 'Добавить новый', 'wc-installment-plans' ),
			'new_item'              => __( 'Новый план', 'wc-installment-plans' ),
			'edit_item'             => __( 'Редактировать план', 'wc-installment-plans' ),
			'update_item'           => __( 'Обновить план', 'wc-installment-plans' ),
			'view_item'             => __( 'Посмотреть план', 'wc-installment-plans' ),
			'view_items'            => __( 'Посмотреть планы', 'wc-installment-plans' ),
			'search_items'          => __( 'Поиск планов', 'wc-installment-plans' ),
			'not_found'             => __( 'Планы не найдены', 'wc-installment-plans' ),
			'not_found_in_trash'    => __( 'Планы не найдены в корзине', 'wc-installment-plans' ),
			'featured_image'        => __( 'Логотип плана', 'wc-installment-plans' ),
			'set_featured_image'    => __( 'Установить логотип плана', 'wc-installment-plans' ),
			'remove_featured_image' => __( 'Удалить логотип плана', 'wc-installment-plans' ),
			'use_featured_image'    => __( 'Использовать как логотип плана', 'wc-installment-plans' ),
			'insert_into_item'      => __( 'Вставить в план', 'wc-installment-plans' ),
			'uploaded_to_this_item' => __( 'Загружено в этот план', 'wc-installment-plans' ),
			'items_list'            => __( 'Список планов', 'wc-installment-plans' ),
			'items_list_navigation' => __( 'Навигация списка планов', 'wc-installment-plans' ),
			'filter_items_list'     => __( 'Фильтр списка планов', 'wc-installment-plans' ),
		];
		
		$args = [
			'label'               => __( 'План рассрочки', 'wc-installment-plans' ),
			'description'         => __( 'Планы рассрочки для WooCommerce', 'wc-installment-plans' ),
			'labels'              => $labels,
			'supports'            => [ 'title', 'thumbnail' ],
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_position'       => 56,
			'menu_icon'           => 'dashicons-money-alt',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		];
		
		register_post_type( self::POST_TYPE, $args );
	}
	
	/**
	 * Добавление пункта меню в WooCommerce
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Планы рассрочки', 'wc-installment-plans' ),
			__( 'Планы рассрочки', 'wc-installment-plans' ),
			'manage_options',
			'edit.php?post_type=' . self::POST_TYPE,
			null
		);
	}
	
	/**
	 * Добавление метабокса для планов
	 */
	public function add_metabox() {
		add_meta_box(
			'wc_installment_plan_details',
			__( 'Детали плана рассрочки', 'wc-installment-plans' ),
			[ $this, 'render_metabox' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}
	
	/**
	 * Рендер метабокса для планов
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'wc_installment_plan_nonce', 'wc_installment_plan_nonce' );
		
		$commissions = get_post_meta( $post->ID, '_wc_installment_commissions', true );
		if ( ! is_array( $commissions ) ) {
			$commissions = [];
		}
		
		?>
		<div class="wc-installment-plan-metabox">
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width: 20%;"><?php echo esc_html__( 'Срок', 'wc-installment-plans' ); ?></th>
						<th style="width: 80%;"><?php echo esc_html__( 'Комиссия (%)', 'wc-installment-plans' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $this->months as $month_value => $month_label ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $month_label ); ?></strong>
							</td>
							<td>
								<input 
									type="number" 
									name="wc_installment_commission[<?php echo esc_attr( $month_value ); ?>]" 
									value="<?php echo isset( $commissions[ $month_value ] ) ? esc_attr( $commissions[ $month_value ] ) : '0'; ?>" 
									step="0.01" 
									min="0" 
									max="100"
									placeholder="0.00"
									style="width: 100%; padding: 8px;"
								>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<p style="margin-top: 20px; font-size: 12px; color: #666;">
				<?php echo esc_html__( 'Укажите комиссию в процентах для каждого срока рассрочки.', 'wc-installment-plans' ); ?>
			</p>
		</div>
		
		<style>
			.wc-installment-plan-metabox {
				padding: 10px 0;
			}
			
			.wc-installment-plan-metabox table {
				margin: 0;
			}
			
			.wc-installment-plan-metabox input[type="number"] {
				border: 1px solid #ddd;
				border-radius: 4px;
				font-size: 14px;
			}
			
			.wc-installment-plan-metabox input[type="number"]:focus {
				border-color: #8224e3;
				box-shadow: 0 0 0 2px rgba(130, 36, 227, 0.1);
				outline: none;
			}
		</style>
		<?php
	}
	
	/**
	 * Сохранение данных метабокса для планов
	 */
	public function save_metabox( $post_id ) {
		if ( ! isset( $_POST['wc_installment_plan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_installment_plan_nonce'] ) ), 'wc_installment_plan_nonce' ) ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( isset( $_POST['wc_installment_commission'] ) && is_array( $_POST['wc_installment_commission'] ) ) {
			$commissions = [];
			
			foreach ( $_POST['wc_installment_commission'] as $month => $commission ) {
				$month = sanitize_text_field( wp_unslash( $month ) );
				$commission = floatval( sanitize_text_field( wp_unslash( $commission ) ) );
				
				if ( isset( $this->months[ $month ] ) && $commission >= 0 && $commission <= 100 ) {
					$commissions[ $month ] = $commission;
				}
			}
			
			update_post_meta( $post_id, '_wc_installment_commissions', $commissions );
		}
	}
	
	/**
	 * Добавление метабокса в товары
	 */
	public function add_product_metabox() {
		add_meta_box(
			'wc_product_installment_plans',
			__( 'Варианты рассрочки', 'wc-installment-plans' ),
			[ $this, 'render_product_metabox' ],
			'product',
			'normal',
			'high'
		);
	}
	
	/**
	 * Рендер метабокса в товарах
	 */
	public function render_product_metabox( $post ) {
		wp_nonce_field( 'wc_product_installment_nonce', 'wc_product_installment_nonce' );
		
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		
		// Получение данных товара
		$installment_enabled = get_post_meta( $post->ID, '_wc_installment_enabled', true );
		$installment_plans = get_post_meta( $post->ID, '_wc_installment_plans', true );
		$installment_months = get_post_meta( $post->ID, '_wc_installment_months', true );
		
		if ( ! is_array( $installment_plans ) ) {
			$installment_plans = [];
		}
		if ( ! is_array( $installment_months ) ) {
			$installment_months = [];
		}
		
		// Получение всех доступных планов
		$all_plans = get_posts( [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		
		?>
		<div class="wc-product-installment-metabox">
			<!-- Включение/выключение рассрочки -->
			<div style="margin-bottom: 20px;">
				<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
					<input type="checkbox" name="wc_installment_enabled" value="1" <?php checked( $installment_enabled, 1 ); ?> class="wc-installment-toggle">
					<span style="font-weight: 600;"><?php echo esc_html__( 'Включить варианты рассрочки для этого товара', 'wc-installment-plans' ); ?></span>
				</label>
			</div>
			
			<!-- Выбор планов -->
			<div class="wc-installment-plans-section" style="display: <?php echo $installment_enabled ? 'block' : 'none'; ?>; padding: 20px; background-color: #f9f9f9; border-radius: 8px;">
				<h3 style="margin-top: 0;"><?php echo esc_html__( 'Выберите планы рассрочки', 'wc-installment-plans' ); ?></h3>
				
				<?php if ( ! empty( $all_plans ) ) : ?>
					<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-bottom: 20px;">
						<?php foreach ( $all_plans as $plan ) : 
							$plan_id = $plan->ID;
							$is_selected = in_array( $plan_id, $installment_plans, true );
							$commissions = get_post_meta( $plan_id, '_wc_installment_commissions', true );
							$logo = get_the_post_thumbnail_url( $plan_id, 'thumbnail' );
							?>
							<div style="border: 2px solid #ddd; border-radius: 8px; padding: 15px; background-color: #fff; transition: all 0.3s;" class="plan-card" data-plan-id="<?php echo esc_attr( $plan_id ); ?>">
								<label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 10px;">
									<input type="checkbox" name="wc_installment_plans[]" value="<?php echo esc_attr( $plan_id ); ?>" <?php checked( $is_selected ); ?> class="plan-checkbox">
									<strong><?php echo esc_html( $plan->post_title ); ?></strong>
								</label>
								
								<?php if ( $logo ) : ?>
									<div style="margin-bottom: 10px;">
										<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $plan->post_title ); ?>" style="max-width: 100%; height: auto; max-height: 60px;">
									</div>
								<?php endif; ?>
								
								<!-- Выбор месяцев для плана -->
								<div class="plan-months" style="display: <?php echo $is_selected ? 'block' : 'none'; ?>; padding-top: 10px; border-top: 1px solid #eee;">
									<label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; color: #666;">
										<?php echo esc_html__( 'Месяцы для отображения:', 'wc-installment-plans' ); ?>
									</label>
									<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
										<?php foreach ( $this->months as $month_value => $month_label ) : 
											$plan_months_key = 'plan_' . $plan_id . '_months';
											$plan_months = isset( $installment_months[ $plan_months_key ] ) ? (array) $installment_months[ $plan_months_key ] : [];
											$is_checked = in_array( $month_value, $plan_months, true );
											?>
											<label style="display: flex; align-items: center; gap: 5px; font-size: 12px; cursor: pointer;">
												<input type="checkbox" name="wc_installment_month_<?php echo esc_attr( $plan_id ); ?>[]" value="<?php echo esc_attr( $month_value ); ?>" <?php checked( $is_checked ); ?> class="month-checkbox">
												<span><?php echo esc_html( $month_value ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div style="padding: 20px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; color: #666;">
						<?php echo esc_html__( 'Сначала создайте планы рассрочки в разделе WooCommerce > Планы рассрочки', 'wc-installment-plans' ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Сохранение данных товара
	 */
	public function save_product_metabox( $post_id ) {
		if ( ! isset( $_POST['wc_product_installment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_product_installment_nonce'] ) ), 'wc_product_installment_nonce' ) ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Сохранение статуса включения
		$enabled = isset( $_POST['wc_installment_enabled'] ) ? 1 : 0;
		update_post_meta( $post_id, '_wc_installment_enabled', $enabled );
		
		// Сохранение выбранных планов
		$plans = [];
		if ( isset( $_POST['wc_installment_plans'] ) && is_array( $_POST['wc_installment_plans'] ) ) {
			$plans = array_map( 'intval', wp_unslash( $_POST['wc_installment_plans'] ) );
		}
		update_post_meta( $post_id, '_wc_installment_plans', $plans );
		
		// Сохранение месяцев для каждого плана
		$months_data = [];
		
		// Получаем все планы
		$all_plans = get_posts( [
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
		] );
		
		foreach ( $all_plans as $plan ) {
			$plan_id = $plan->ID;
			$months_field = 'wc_installment_month_' . $plan_id;
			
			if ( isset( $_POST[ $months_field ] ) && is_array( $_POST[ $months_field ] ) ) {
				$plan_months_key = 'plan_' . $plan_id . '_months';
				$months_data[ $plan_months_key ] = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $months_field ] ) );
			}
		}
		
		update_post_meta( $post_id, '_wc_installment_months', $months_data );
	}
	
	/**
	 * Загрузка JS для админки
	 */
	public function enqueue_admin_js( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		
		global $post;
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}
		
		?>
		<script>
		jQuery(document).ready(function($) {
			const $checkbox = $('.wc-installment-toggle');
			const $section = $('.wc-installment-plans-section');
			
			// Переключение видимости при изменении чекбокса
			$checkbox.on('change', function() {
				if ($(this).is(':checked')) {
					$section.slideDown();
				} else {
					$section.slideUp();
				}
			});
			
			// Переключение месяцев при выборе плана
			$('.plan-checkbox').on('change', function() {
				const $card = $(this).closest('.plan-card');
				const $months = $card.find('.plan-months');
				
				if ($(this).is(':checked')) {
					$months.slideDown();
				} else {
					$months.slideUp();
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Загрузка стилей и скриптов
	 */
	public function enqueue_assets( $hook ) {
		global $post_type;
		
		if ( $post_type === self::POST_TYPE ) {
			wp_enqueue_style( 'wc-installment-plans-admin', WC_INSTALLMENT_PLANS_URL . 'assets/admin.css', [], WC_INSTALLMENT_PLANS_VERSION );
		}
	}
	
	/**
	 * Кастомные колонки в списке постов
	 */
	public function custom_columns( $columns ) {
		$new_columns = [];
		
		foreach ( $columns as $key => $value ) {
			if ( $key === 'title' ) {
				$new_columns[ $key ] = $value;
				$new_columns['logo'] = __( 'Логотип', 'wc-installment-plans' );
			} else {
				$new_columns[ $key ] = $value;
			}
		}
		
		return $new_columns;
	}
	
	/**
	 * Содержимое кастомных колонок
	 */
	public function custom_column_content( $column, $post_id ) {
		if ( $column === 'logo' ) {
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, [ 50, 50 ] );
			} else {
				echo '<em>' . esc_html__( 'Нет изображения', 'wc-installment-plans' ) . '</em>';
			}
		}
	}
	
	/**
	 * Получение месяцев
	 */
	public function get_months() {
		return $this->months;
	}
}