<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Installation_Plan {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	public function register_post_type() {
		$args = [
			'label'               => 'Планы рассрочки',
			'description'         => 'Планы рассрочки для WooCommerce',
			'labels'              => [
				'name'          => 'Планы рассрочки',
				'singular_name' => 'План рассрочки',
				'add_new'       => 'Добавить новый',
				'add_new_item'  => 'Добавить новый план',
				'edit_item'     => 'Редактировать план',
				'new_item'      => 'Новый план',
				'view_item'     => 'Просмотр плана',
				'search_items'  => 'Поиск планов',
				'not_found'     => 'Планы не найдены',
				'menu_name'     => 'Планы рассрочки',
			],
			'supports'            => [ 'title', 'thumbnail' ],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_icon'           => 'dashicons-money-alt',
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
		];

		register_post_type( 'wc_installment_plan', $args );
	}
}