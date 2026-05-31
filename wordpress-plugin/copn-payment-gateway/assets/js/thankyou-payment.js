/**
 * CheckoutPay thank-you page: check status and update paid amount.
 */
(function ($) {
	'use strict';

	if (typeof window.copnThankyou === 'undefined') {
		return;
	}

	var config = window.copnThankyou;
	var i18n = config.i18n || {};

	$('#copn-check-status').on('click', function () {
		var button = $(this);
		button.prop('disabled', true).text(i18n.checking || 'Checking...');

		$.ajax({
			url: config.checkStatusUrl,
			type: 'GET',
			data: {
				order_id: config.orderId,
				nonce: config.nonce
			},
			success: function (response) {
				if (response.success && response.data.status === 'completed') {
					window.location.reload();
				} else {
					button.prop('disabled', false).text(i18n.checkStatus || '');
					window.alert(i18n.stillPending || '');
				}
			},
			error: function () {
				button.prop('disabled', false).text(i18n.checkStatus || '');
				window.alert(i18n.checkError || '');
			}
		});
	});

	$('#copn-update-amount-btn').on('click', function () {
		var button = $(this);
		var amountInput = $('#copn-actual-amount');
		var amount = parseFloat(amountInput.val());

		if (!amount || amount <= 0) {
			window.alert(i18n.enterAmount || '');
			return;
		}

		button.prop('disabled', true).text(i18n.updating || 'Updating...');

		$.ajax({
			url: config.updateAmountUrl,
			type: 'POST',
			data: {
				order_id: config.orderId,
				amount: amount,
				nonce: config.nonce
			},
			success: function (response) {
				if (response.success) {
					if (response.data && response.data.status === 'completed') {
						window.location.reload();
					} else {
						button.prop('disabled', false).text(i18n.updateAmount || '');
						window.alert(
							response.data && response.data.message
								? response.data.message
								: (i18n.amountUpdated || '')
						);
					}
				} else {
					button.prop('disabled', false).text(i18n.updateAmount || '');
					window.alert(
						response.data && response.data.message
							? response.data.message
							: (i18n.updateFailed || '')
					);
				}
			},
			error: function (xhr) {
				button.prop('disabled', false).text(i18n.updateAmount || '');
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					? xhr.responseJSON.data.message
					: (i18n.updateError || '');
				window.alert(msg);
			}
		});
	});
})(jQuery);
