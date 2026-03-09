jQuery(document).ready(function($) {
	// Клик по вкладкам месяцев
	$(document).on('click', '.installment-month-tab', function(e) {
		e.preventDefault();
		
		const month = $(this).data('month');
		const $section = $(this).closest('.installment-section');
		
		// Обновляем вкладки
		$section.find('.installment-month-tab').removeClass('active');
		$(this).addClass('active');
		
		// Обновляем контент
		$section.find('.installment-plans-group').removeClass('active');
		$section.find('.installment-plans-group[data-month="' + month + '"]').addClass('active');
		
		// Находим активный план в этой группе и обновляем цену
		updateTotalPriceFromActivePlan($section);
	});
	
	// Клик по планам рассрочки
	$(document).on('click', '.installment-plan', function(e) {
		e.preventDefault();
		
		const $section = $(this).closest('.installment-section');
		const total = $(this).data('total');
		
		// Удаляем активный класс со всех планов в активной группе
		$section.find('.installment-plans-group.active .installment-plan').removeClass('active');
		
		// Добавляем активный класс к выбранному плану
		$(this).addClass('active');
		
		// Обновляем общую сумму из выбранного плана
		const formattedPrice = formatPrice(total);
		$section.find('.installment-total-price').text(formattedPrice);
	});
	
	// Функция для обновления суммы на основе активного плана в текущей группе
	function updateTotalPriceFromActivePlan($section) {
		const $activePlan = $section.find('.installment-plans-group.active .installment-plan.active');
		
		if ($activePlan.length) {
			// Если есть активный план, используем его
			const total = $activePlan.data('total');
			const formattedPrice = formatPrice(total);
			$section.find('.installment-total-price').text(formattedPrice);
		} else {
			// Иначе используем первый план в группе
			const $firstPlan = $section.find('.installment-plans-group.active .installment-plan').first();
			if ($firstPlan.length) {
				const total = $firstPlan.data('total');
				const formattedPrice = formatPrice(total);
				$section.find('.installment-total-price').text(formattedPrice);
			}
		}
	}
	
	// Функция для форматирования цены
	function formatPrice(price) {
		return new Intl.NumberFormat('ru-RU', {
			style: 'currency',
			currency: 'UZS',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(price);
	}

	// Обновление блока рассрочки после выбора вариации
	function updateInstallmentBlock($section, data) {
		var plans = data.plans;

		$section.find('.installment-plans-group').each(function() {
			var month = String($(this).data('month'));
			if (!plans[month]) {
				return;
			}

			$(this).find('.installment-plan').each(function(index) {
				if (!plans[month][index]) {
					return;
				}
				var planData = plans[month][index];
				$(this).attr('data-total', planData.total).data('total', planData.total);
				$(this).attr('data-monthly', planData.monthly).data('monthly', planData.monthly);
				$(this).find('.installment-plan-price').html(formatPrice(planData.monthly) + '/месяц');
			});
		});

		updateTotalPriceFromActivePlan($section);
	}

	// Сброс блока рассрочки при отмене выбора вариации
	function resetInstallmentBlock($section) {
		$section.find('.installment-plan').each(function() {
			var $plan = $(this);
			var originalTotal = $plan.data('original-total');
			var originalMonthly = $plan.data('original-monthly');
			if (originalTotal !== undefined) {
				$plan.attr('data-total', originalTotal).data('total', originalTotal);
			}
			if (originalMonthly !== undefined) {
				$plan.attr('data-monthly', originalMonthly).data('monthly', originalMonthly);
				$plan.find('.installment-plan-price').html(formatPrice(originalMonthly) + '/месяц');
			}
		});

		updateTotalPriceFromActivePlan($section);
	}

	// Обработка вариативных товаров
	var $variableSection = $('.installment-section[data-is-variable="1"]');
	if ($variableSection.length) {
		var $form = $('form.variations_form');

		$form.on('found_variation', function(event, variation) {
			var variationPrice = parseFloat(variation.display_price);
			if (!variationPrice) {
				return;
			}

			var productId = $variableSection.data('product-id');

			$.ajax({
				url: wcInstallmentAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'update_installment_variation',
					nonce: wcInstallmentAjax.nonce,
					product_id: productId,
					price: variationPrice
				},
				success: function(response) {
					if (response.success) {
						updateInstallmentBlock($variableSection, response.data);
					}
				},
				error: function() {
					resetInstallmentBlock($variableSection);
				}
			});
		});

		$form.on('reset_data', function() {
			resetInstallmentBlock($variableSection);
		});

		// Обработка дополнительных опций (соусы, степень прожарки и т.д.),
		// которые изменяют отображаемую цену без смены вариации.
		// Слушаем всю форму, так как поля дополнительных опций могут иметь
		// разные селекторы в зависимости от используемого плагина.
		var optionChangeTimer = null;
		$form.on('change', function() {
			clearTimeout(optionChangeTimer);
			optionChangeTimer = setTimeout(function() {
				// Читаем цену только если блок цены вариации виден
				// (т.е. вариация уже выбрана и цена обновлена плагинами опций)
				var $variationPriceWrap = $('.woocommerce-variation-price');
				if (!$variationPriceWrap.length || !$variationPriceWrap.is(':visible')) {
					return;
				}

				// Берём первый .amount внутри блока цены вариации
				// (при наличии старой и новой цены — первый элемент является актуальной)
				var $priceEl = $variationPriceWrap.find('.amount').first();
				if (!$priceEl.length) {
					return;
				}

				// Парсим цену: убираем все нецифровые символы.
				// Подходит для UZS и других валют без десятичных знаков.
				var digits = $priceEl.text().replace(/[^\d]/g, '');
				var price = parseFloat(digits);
				if (!price || price <= 0) {
					return;
				}

				var productId = $variableSection.data('product-id');

				$.ajax({
					url: wcInstallmentAjax.ajaxUrl,
					type: 'POST',
					data: {
						action: 'update_installment_variation',
						nonce: wcInstallmentAjax.nonce,
						product_id: productId,
						price: price
					},
					success: function(response) {
						if (response.success) {
							updateInstallmentBlock($variableSection, response.data);
						}
					}
				});
			}, 300);
		});
	}
});
