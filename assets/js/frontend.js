/**
 * In-Stock Notifier frontend JavaScript.
 *
 * Handles AJAX form submission and variable product variation detection.
 *
 * @package InStockNotifier
 */

/* global jQuery, bisn_vars */
(function ($) {
	'use strict';

	/**
	 * Handle form submission via AJAX.
	 */
	function handleFormSubmit(e) {
		e.preventDefault();

		var $form = $(this);
		var $btn = $form.find('.bisn-submit');
		var $msg = $form.closest('.bisn-notify-form').find('.bisn-form-message');

		if ($btn.prop('disabled')) {
			return;
		}

		$btn.prop('disabled', true);
		$msg.text('').removeClass('bisn-success bisn-error');

		var data = {
			action: 'bisn_subscribe',
			bisn_nonce: bisn_vars.nonce,
			bisn_email: $form.find('[name="bisn_email"]').val(),
			bisn_product_id: $form.find('[name="bisn_product_id"]').val(),
			bisn_variation_id: $form.find('[name="bisn_variation_id"]').val() || '0',
			bisn_quantity: $form.find('[name="bisn_quantity"]').val() || '1',
			bisn_gdpr: $form.find('[name="bisn_gdpr"]').is(':checked') ? '1' : '',
			bisn_website: $form.find('[name="bisn_website"]').val() || ''
		};

		$.post(bisn_vars.ajax_url, data, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$msg.text(response.data.message).addClass('bisn-success');
				$form.find('[name="bisn_email"]').val('');
			} else {
				var message = response.data && response.data.message
					? response.data.message
					: bisn_vars.error_generic;
				$msg.text(message).addClass('bisn-error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$msg.text(bisn_vars.error_network).addClass('bisn-error');
		});
	}

	$(function () {
		$(document.body).on('submit', '.bisn-form', handleFormSubmit);

		$(document.body).on('found_variation', '.variations_form', function (event, variation) {
			var $form = $(event.target).closest('.product').find('.bisn-notify-form');
			if (!$form.length) { return; }

			if (variation && !variation.is_in_stock) {
				$form.find('[name="bisn_variation_id"]').val(variation.variation_id);
				$form.stop(true).slideDown(200);
			} else {
				$form.stop(true).slideUp(200);
			}
		});

		$(document.body).on('reset_data', '.variations_form', function (event) {
			var $form = $(event.target).closest('.product').find('.bisn-notify-form');
			if ($form.length) {
				$form.stop(true).slideUp(200);
			}
		});
	});
})(jQuery);
