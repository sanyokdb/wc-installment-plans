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
		var months = data.months;

		$section.find('.installment-plans-group').each(function() {
			var month = String($(this).data('month'));
			if (!months[month]) {
				return;
			}

			// Build a lookup map from plan_id to plan data for reliable matching.
			var planMap = {};
			for (var i = 0; i < months[month].length; i++) {
				planMap[String(months[month][i].plan_id)] = months[month][i];
			}

			$(this).find('.installment-plan').each(function() {
				var planId = String($(this).data('plan-id'));
				var planData = planMap[planId];
				if (!planData) {
					return;
				}
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

		// Кэш последней отправленной цены и контроллер текущего запроса
		var lastInstallmentPrice = null;
		var pendingRequest = null;
		var optionChangeTimer = null;

		// Отправляет AJAX-запрос только если цена изменилась.
		// Отменяет предыдущий запрос, если он ещё не завершился.
		function sendInstallmentUpdate(price) {
			if (price === lastInstallmentPrice) {
				return;
			}
			lastInstallmentPrice = price;

			if (pendingRequest) {
				pendingRequest.abort();
				pendingRequest = null;
			}

			var controller = new AbortController();
			pendingRequest = controller;
			var timeoutId = setTimeout(function() { controller.abort(); }, 5000);

			fetch(wcInstallmentAjax.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'update_installment_variation',
					nonce: wcInstallmentAjax.nonce,
					product_id: $variableSection.data('product-id'),
					price: price
				}).toString(),
				signal: controller.signal
			})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function(data) {
				clearTimeout(timeoutId);
				pendingRequest = null;
				if (data.success) {
					updateInstallmentBlock($variableSection, data.data);
				}
			})
			.catch(function(err) {
				clearTimeout(timeoutId);
				pendingRequest = null;
				// Abort errors are intentional (new request superseded this one)
				if (err && err.name === 'AbortError') {
					return;
				}
			});
		}

		$form.on('found_variation', function(event, variation) {
			var variationPrice = parseFloat(variation.display_price);
			if (!variationPrice) {
				return;
			}
			// Immediately update with the base variation price.
			sendInstallmentUpdate(variationPrice);

			// After a short delay, re-read the DOM price to catch any addon-plugin
			// adjustments (e.g. "Соусы", "Степень прожарки") that modify the
			// displayed price asynchronously after found_variation fires.
			clearTimeout(optionChangeTimer);
			optionChangeTimer = setTimeout(function() {
				var domPrice = readDomVariationPrice();
				if (domPrice) {
					sendInstallmentUpdate(domPrice);
				}
			}, 400);
		});

		$form.on('reset_data', function() {
			lastInstallmentPrice = null;
			clearTimeout(optionChangeTimer);
			if (pendingRequest) {
				pendingRequest.abort();
				pendingRequest = null;
			}
			resetInstallmentBlock($variableSection);
		});

		// Helper: reads the current displayed variation price from the DOM.
		// Prefers the sale price (ins > .amount) to avoid picking up
		// the struck-through regular price when a sale is active.
		function readDomVariationPrice() {
			var $wrap = $('.woocommerce-variation-price');
			if (!$wrap.length || !$wrap.is(':visible')) {
				return null;
			}
			// Prefer ins (sale price), fall back to first .amount
			var $el = $wrap.find('ins .amount');
			if (!$el.length) {
				$el = $wrap.find('.amount').first();
			}
			if (!$el.length) {
				return null;
			}
			// Strip all non-digit characters. Works for UZS (integer currency).
			var digits = $el.text().replace(/[^\d]/g, '');
			var price = parseInt(digits, 10);
			return (price && price > 0) ? price : null;
		}

		// Обработка дополнительных опций (соусы, степень прожарки и т.д.),
		// которые изменяют отображаемую цену без смены вариации.
		// Слушаем всю форму, так как поля дополнительных опций могут иметь
		// разные селекторы в зависимости от используемого плагина.
		$form.on('change', function() {
			clearTimeout(optionChangeTimer);
			optionChangeTimer = setTimeout(function() {
				var domPrice = readDomVariationPrice();
				if (domPrice) {
					sendInstallmentUpdate(domPrice);
				}
			}, 400);
		});
	}
});
