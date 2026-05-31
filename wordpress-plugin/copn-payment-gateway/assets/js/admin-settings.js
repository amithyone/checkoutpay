/**
 * CheckoutPay WooCommerce settings: copy webhook and website URLs.
 */
(function () {
	'use strict';

	function copyInputValue(inputId, buttonId, copiedLabel) {
		var input = document.getElementById(inputId);
		var button = document.getElementById(buttonId);
		if (!input || !button) {
			return;
		}

		var originalText = button.textContent;

		function showCopied() {
			button.textContent = copiedLabel;
			setTimeout(function () {
				button.textContent = originalText;
			}, 2000);
		}

		button.addEventListener('click', function () {
			input.select();
			input.setSelectionRange(0, 99999);

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(input.value).then(showCopied).catch(function () {
					try {
						document.execCommand('copy');
						showCopied();
					} catch (e) {
						window.alert(input.value);
					}
				});
			} else {
				try {
					document.execCommand('copy');
					showCopied();
				} catch (e) {
					window.alert(input.value);
				}
			}
		});
	}

	if (typeof window.copnAdminSettings === 'undefined') {
		return;
	}

	var config = window.copnAdminSettings;
	var copiedLabel = config.copiedLabel || 'Copied!';
	var pairs = config.pairs || [];

	pairs.forEach(function (pair) {
		copyInputValue(pair.inputId, pair.buttonId, copiedLabel);
	});

	['copn-portal-url', 'copn-webhook-url', 'copn-website-url'].forEach(function (inputId) {
		var input = document.getElementById(inputId);
		if (input) {
			input.addEventListener('focus', function () {
				input.select();
			});
		}
	});
})();
