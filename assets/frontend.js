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
});