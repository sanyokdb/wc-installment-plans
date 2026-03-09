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
		var placeholder = '<span class="installment-variation-placeholder">Выберите вариацию</span>';
		var minPrice = $section.data('min-price');
		var maxPrice = $section.data('max-price');

		$section.find('.installment-plan-price').html(placeholder);

		if (minPrice && maxPrice) {
			$section.find('.installment-total-price').html(
				formatPrice(minPrice) + ' — ' + formatPrice(maxPrice)
			);
		} else {
			$section.find('.installment-total-price').html(placeholder);
		}
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
	}
});
