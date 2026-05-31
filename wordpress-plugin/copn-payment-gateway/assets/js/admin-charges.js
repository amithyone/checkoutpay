/**
 * CheckoutPay WooCommerce settings: load charge settings from API.
 */
(function () {
	'use strict';

	if (typeof window.copnAdminCharges === 'undefined') {
		return;
	}

	var config = window.copnAdminCharges;
	var i18n = config.i18n || {};
	var panel = document.getElementById('copn-charges-panel');
	var loading = document.getElementById('copn-charges-loading');
	var refreshBtn = document.getElementById('copn-refresh-charges');

	if (!panel || !refreshBtn) {
		return;
	}

	function getSettingInput(suffix) {
		return document.getElementById('woocommerce_checkoutpay_' + suffix)
			|| document.querySelector('[name="woocommerce_checkoutpay_' + suffix + '"]');
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text == null ? '' : String(text);
		return div.innerHTML;
	}

	function formatMoney(amount) {
		var n = parseFloat(amount);
		if (isNaN(n)) {
			return '—';
		}
		return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function renderError(message) {
		panel.innerHTML = '<p style="margin:0;color:#b32d2e;"><strong>' + escapeHtml(i18n.unableToLoad || 'Unable to load charges') + '</strong> ' + escapeHtml(message) + '</p>';
	}

	function renderData(payload) {
		var d = payload.data || {};
		var sample = d.sample || {};
		var feeLine = '';

		if (!d.charges_enabled || d.charge_exempt) {
			feeLine = i18n.noCharges || '';
		} else {
			feeLine = escapeHtml(d.charge_percentage) + '% + ' + formatMoney(d.charge_fixed);
		}

		var html = '';
		html += '<table class="widefat striped" style="margin:0;background:#fff;"><tbody>';
		html += '<tr><th style="width:40%;">' + escapeHtml(i18n.matchedWebsite || '') + '</th><td>' + escapeHtml((d.website && d.website.url) ? d.website.url : '—') + '</td></tr>';
		html += '<tr><th>' + escapeHtml(i18n.feeStructure || '') + '</th><td>' + feeLine + '</td></tr>';
		html += '<tr><th>' + escapeHtml(i18n.whoPaysFees || '') + '</th><td>' + escapeHtml(d.paid_by_label || '—') + '</td></tr>';
		html += '<tr><th>' + escapeHtml(i18n.sampleOrder || '') + '</th><td>' + formatMoney(d.sample_amount) + '</td></tr>';
		html += '<tr><th>' + escapeHtml(i18n.feesOnSample || '') + '</th><td>' + formatMoney(sample.total_charges) + '</td></tr>';
		html += '<tr><th>' + escapeHtml(i18n.customerTransfers || '') + '</th><td><strong>' + formatMoney(sample.amount_to_pay) + '</strong></td></tr>';
		html += '<tr><th>' + escapeHtml(i18n.youReceive || '') + '</th><td><strong>' + formatMoney(sample.business_receives) + '</strong></td></tr>';
		html += '</tbody></table>';

		if (d.dashboard_note) {
			html += '<p class="description" style="margin:10px 0 0;">' + escapeHtml(d.dashboard_note) + '</p>';
		}

		var settingsUrl = (d.dashboard_websites_url && String(d.dashboard_websites_url).indexOf('http') === 0)
			? d.dashboard_websites_url
			: config.dashboardWebsitesUrl;
		var portalLink = (d.portal_url && String(d.portal_url).indexOf('http') === 0) ? d.portal_url : config.portalUrl;

		html += '<p style="margin:8px 0 0;"><a href="' + escapeHtml(settingsUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.openSettings || '') + '</a> · <a href="' + escapeHtml(portalLink) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.checkoutpayHome || '') + '</a></p>';
		panel.innerHTML = html;
	}

	function loadCharges() {
		var apiUrlInput = getSettingInput('api_url');
		var apiKeyInput = getSettingInput('api_key');
		var apiUrl = apiUrlInput ? String(apiUrlInput.value || '').replace(/\/+$/, '') : '';
		var apiKey = apiKeyInput ? String(apiKeyInput.value || '') : '';

		if (!apiUrl || !apiKey) {
			renderError(i18n.apiRequired || '');
			return;
		}

		if (loading) {
			loading.style.display = 'inline';
		}
		refreshBtn.disabled = true;

		var url = apiUrl + '/integration/charge-settings?website_url=' + encodeURIComponent(config.websiteUrl)
			+ '&webhook_url=' + encodeURIComponent(config.webhookUrl)
			+ '&sample_amount=' + encodeURIComponent(String(config.sampleAmount));

		window.fetch(url, {
			method: 'GET',
			headers: {
				Accept: 'application/json',
				'X-API-Key': apiKey
			}
		})
			.then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, status: res.status, body: body };
				});
			})
			.then(function (result) {
				if (result.ok && result.body && result.body.success) {
					renderData(result.body);
				} else {
					var msg = (result.body && result.body.message) ? result.body.message : ('HTTP ' + result.status);
					renderError(msg);
				}
			})
			.catch(function (err) {
				renderError(err && err.message ? err.message : (i18n.networkError || ''));
			})
			.finally(function () {
				if (loading) {
					loading.style.display = 'none';
				}
				refreshBtn.disabled = false;
			});
	}

	refreshBtn.addEventListener('click', loadCharges);
})();
