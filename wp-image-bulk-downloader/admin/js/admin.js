(function ($) {
	'use strict';

	var $start = $('#wpibd-start');
	var $cancel = $('#wpibd-cancel');
	var $progress = $('#wpibd-progress');
	var $progressFill = $('#wpibd-progress-fill');
	var $status = $('#wpibd-progress-status');
	var $notice = $('#wpibd-notice');

	var currentJobId = null;
	var inFlight = false;
	var beforeUnloadHandler = function (e) {
		var msg = wpibdConfig.i18n.confirmLeave;
		(e || window.event).returnValue = msg;
		return msg;
	};

	function setNotice(message, kind) {
		$notice.removeClass('is-error is-success');
		if (kind) {
			$notice.addClass('is-' + kind);
		}
		$notice.html(message).prop('hidden', false);
	}

	function clearNotice() {
		$notice.prop('hidden', true).empty().removeClass('is-error is-success');
	}

	function setStatus(text) {
		$status.text(text);
	}

	function setProgress(processed, total) {
		var pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		$progressFill.css('width', pct + '%');
	}

	function enterRunningState() {
		inFlight = true;
		$start.prop('disabled', true);
		$cancel.prop('hidden', false);
		$progress.prop('hidden', false);
		clearNotice();
		window.addEventListener('beforeunload', beforeUnloadHandler);
	}

	function exitRunningState() {
		inFlight = false;
		$start.prop('disabled', false);
		$cancel.prop('hidden', true);
		window.removeEventListener('beforeunload', beforeUnloadHandler);
	}

	function startExport() {
		var mode = $('input[name="wpibd_mode"]:checked').val() || 'images_only';
		var includeSiteInfo = $('#wpibd-include-site-info').is(':checked') ? '1' : '0';
		var downloadAsGz = $('#wpibd-download-as-gz').is(':checked') ? '1' : '0';

		enterRunningState();
		setStatus(wpibdConfig.i18n.preparing);
		setProgress(0, 1);

		$.post(wpibdConfig.ajaxUrl, {
			action: 'wpibd_start_export',
			nonce: wpibdConfig.nonce,
			mode: mode,
			include_site_info: includeSiteInfo,
			download_as_gz: downloadAsGz
		}).done(function (response) {
			if (!response || !response.success) {
				handleError(response && response.data ? response.data.message : null);
				return;
			}
			currentJobId = response.data.job_id;
			processChunk(0, response.data.total);
		}).fail(function (xhr) {
			handleError(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : null);
		});
	}

	function processChunk(offset, total) {
		if (!currentJobId) {
			return;
		}

		$.post(wpibdConfig.ajaxUrl, {
			action: 'wpibd_export_chunk',
			nonce: wpibdConfig.nonce,
			job_id: currentJobId,
			offset: offset
		}).done(function (response) {
			if (!response || !response.success) {
				handleError(response && response.data ? response.data.message : null);
				return;
			}

			var data = response.data;
			setProgress(data.processed, data.total);
			setStatus(
				wpibdConfig.i18n.processing
					.replace('%1$s', data.processed.toLocaleString())
					.replace('%2$s', data.total.toLocaleString())
			);

			if (data.done) {
				finishExport(data);
				return;
			}

			processChunk(data.next, total);
		}).fail(function (xhr) {
			handleError(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : null);
		});
	}

	function finishExport(data) {
		setProgress(data.total, data.total);

		var message = wpibdConfig.i18n.complete;
		if (data.failed && data.failed > 0) {
			message += ' ' + wpibdConfig.i18n.failed.replace('%s', data.failed.toLocaleString());
		}

		setNotice(message, 'success');
		setStatus('');

		if (data.download_url) {
			window.location.href = data.download_url;
		}

		currentJobId = null;
		exitRunningState();
	}

	function handleError(message) {
		var text = wpibdConfig.i18n.error + ' ' + (message || '');
		setNotice(text, 'error');
		setStatus('');
		setProgress(0, 1);
		$progress.prop('hidden', true);
		currentJobId = null;
		exitRunningState();
	}

	function cancelExport() {
		if (!currentJobId) {
			exitRunningState();
			return;
		}

		var jobId = currentJobId;
		currentJobId = null;

		$.post(wpibdConfig.ajaxUrl, {
			action: 'wpibd_cancel_export',
			nonce: wpibdConfig.nonce,
			job_id: jobId
		});

		setNotice(wpibdConfig.i18n.cancelled, 'error');
		setStatus('');
		setProgress(0, 1);
		exitRunningState();
	}

	$start.on('click', function () {
		if (inFlight) {
			return;
		}
		startExport();
	});

	$cancel.on('click', function () {
		cancelExport();
	});
})(jQuery);
