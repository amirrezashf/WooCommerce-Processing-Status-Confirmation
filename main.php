<?php
/**
 * Plugin Name:       WooCommerce Processing Status Confirmation
 * Plugin URI:        https://github.com/amirrezashf/WooCommerce-Processing-Status-Confirmation
 * Description:       Requires an exact admin confirmation before changing a WooCommerce order status to processing.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Amirreza Shayesteh Far
 * Author URI:        https://github.com/amirrezashf
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-processing-status-confirmation
 * Domain Path:       /languages
 *
 * @package WooCommerceProcessingStatusConfirmation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Processing_Status_Confirmation' ) ) {
	/**
	 * Main plugin class.
	 */
	final class WC_Processing_Status_Confirmation {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		private const VERSION = '1.0.0';

		/**
		 * Script handle.
		 *
		 * @var string
		 */
		private const SCRIPT_HANDLE = 'wc-processing-status-confirmation';

		/**
		 * Required confirmation phrase.
		 *
		 * @var string
		 */
		private const CONFIRMATION_PHRASE = 'بله';

		/**
		 * Nonce action.
		 *
		 * @var string
		 */
		private const NONCE_ACTION = 'wc_psc_processing_confirmation';

		/**
		 * Nonce field name.
		 *
		 * @var string
		 */
		private const NONCE_FIELD = '_wc_psc_processing_confirmation_nonce';

		/**
		 * Confirmation flag field name.
		 *
		 * @var string
		 */
		private const CONFIRMED_FIELD = 'wc_psc_processing_confirmed';

		/**
		 * Confirmation phrase field name.
		 *
		 * @var string
		 */
		private const PHRASE_FIELD = 'wc_psc_processing_phrase';

		/**
		 * Admin notice transient prefix.
		 *
		 * @var string
		 */
		private const NOTICE_TRANSIENT_PREFIX = 'wc_psc_notice_';

		/**
		 * Singleton instance.
		 *
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * Get singleton instance.
		 *
		 * @return self
		 */
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		}

		/**
		 * Declare WooCommerce HPOS compatibility.
		 *
		 * @return void
		 */
		public function declare_hpos_compatibility(): void {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					__FILE__,
					true
				);
			}
		}

		/**
		 * Load plugin textdomain.
		 *
		 * @return void
		 */
		public function load_textdomain(): void {
			load_plugin_textdomain(
				'woocommerce-processing-status-confirmation',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages'
			);
		}

		/**
		 * Initialize plugin.
		 *
		 * @return void
		 */
		public function init(): void {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
				return;
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_script' ), 20 );
			add_filter( 'wp_insert_post_data', array( $this, 'block_legacy_order_processing_without_confirmation' ), 10, 2 );
			add_action( 'woocommerce_before_order_object_save', array( $this, 'block_hpos_order_processing_without_confirmation' ), 10, 2 );
			add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		}

		/**
		 * Render WooCommerce missing notice.
		 *
		 * @return void
		 */
		public function render_missing_woocommerce_notice(): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'WooCommerce Processing Status Confirmation requires WooCommerce to be installed and active.', 'woocommerce-processing-status-confirmation' );
			echo '</p></div>';
		}

		/**
		 * Enqueue inline script on WooCommerce single order admin screens.
		 *
		 * @param string $hook_suffix Current admin hook suffix.
		 *
		 * @return void
		 */
		public function enqueue_admin_script( string $hook_suffix ): void {
			unset( $hook_suffix );

			if ( ! $this->is_order_edit_screen() || ! current_user_can( $this->get_required_capability() ) ) {
				return;
			}

			wp_register_script(
				self::SCRIPT_HANDLE,
				false,
				array( 'jquery' ),
				self::VERSION,
				true
			);

			wp_enqueue_script( self::SCRIPT_HANDLE );

			wp_add_inline_script(
				self::SCRIPT_HANDLE,
				'window.wcPscConfirmation = ' . wp_json_encode(
					array(
						'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
						'fields'   => array(
							'nonce'     => self::NONCE_FIELD,
							'confirmed' => self::CONFIRMED_FIELD,
							'phrase'    => self::PHRASE_FIELD,
						),
						'settings' => array(
							'targetStatuses' => array( 'wc-processing', 'processing' ),
							'exactPhrase'    => self::CONFIRMATION_PHRASE,
						),
						'i18n'     => array(
							'title'        => __( 'تأیید تغییر وضعیت', 'woocommerce-processing-status-confirmation' ),
							'message'      => __( 'لطفاً تأیید کنید که سفارش را از همه جوانب و به‌خصوص نداشتن «ما به‌التفاوت» بررسی کرده‌اید. برای ادامه، دقیقاً عبارت «بله» را وارد کنید.', 'woocommerce-processing-status-confirmation' ),
							'inputLabel'   => __( 'عبارت تأیید', 'woocommerce-processing-status-confirmation' ),
							'cancel'       => __( 'انصراف', 'woocommerce-processing-status-confirmation' ),
							'confirm'      => __( 'تأیید', 'woocommerce-processing-status-confirmation' ),
							'error'        => __( 'برای تأیید باید دقیقاً «بله» نوشته شود.', 'woocommerce-processing-status-confirmation' ),
							'modalAria'    => __( 'پنجره تأیید تغییر وضعیت سفارش', 'woocommerce-processing-status-confirmation' ),
						),
					)
				) . ';',
				'before'
			);

			wp_add_inline_script(
				self::SCRIPT_HANDLE,
				$this->get_inline_js()
			);
		}

		/**
		 * Block classic post-based order status change without confirmation.
		 *
		 * @param array<string,mixed> $data    Post data.
		 * @param array<string,mixed> $postarr Raw post array.
		 *
		 * @return array<string,mixed>
		 */
		public function block_legacy_order_processing_without_confirmation( array $data, array $postarr ): array {
			if ( ! $this->is_admin_single_order_save_request() ) {
				return $data;
			}

			if ( empty( $postarr['post_type'] ) || 'shop_order' !== $postarr['post_type'] ) {
				return $data;
			}

			if ( empty( $data['post_status'] ) || ! $this->is_processing_status( (string) $data['post_status'] ) ) {
				return $data;
			}

			if ( ! current_user_can( $this->get_required_capability() ) ) {
				return $data;
			}

			$order_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;

			if ( ! $order_id ) {
				return $data;
			}

			if ( $this->request_has_valid_confirmation() ) {
				return $data;
			}

			$current_status = get_post_status( $order_id );

			$data['post_status'] = $current_status ? $current_status : 'wc-pending';

			$this->queue_blocked_notice( $order_id );

			return $data;
		}

		/**
		 * Block HPOS order object status change without confirmation.
		 *
		 * @param mixed $order      Order object.
		 * @param mixed $data_store Data store.
		 *
		 * @return void
		 */
		public function block_hpos_order_processing_without_confirmation( $order, $data_store ): void {
			unset( $data_store );

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( ! $this->is_admin_single_order_save_request() ) {
				return;
			}

			if ( ! current_user_can( $this->get_required_capability() ) ) {
				return;
			}

			$changes = $order->get_changes();

			if ( empty( $changes['status'] ) || ! $this->is_processing_status( (string) $changes['status'] ) ) {
				return;
			}

			if ( $this->request_has_valid_confirmation() ) {
				return;
			}

			$order_id        = $order->get_id();
			$original_status = $this->get_original_order_status( $order );

			if ( $original_status ) {
				$order->set_status( $original_status );
			}

			if ( $order_id ) {
				$this->queue_blocked_notice( $order_id );
			}
		}

		/**
		 * Get original order status before pending changes are saved.
		 *
		 * @param WC_Order $order Order object.
		 *
		 * @return string
		 */
		private function get_original_order_status( WC_Order $order ): string {
			$data = $order->get_data();

			if ( isset( $data['status'] ) && is_string( $data['status'] ) && '' !== $data['status'] ) {
				return $data['status'];
			}

			$status = $order->get_status( 'edit' );

			return is_string( $status ) ? $status : 'pending';
		}

		/**
		 * Check if current screen is an order edit screen.
		 *
		 * @return bool
		 */
		private function is_order_edit_screen(): bool {
			if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();

			if ( ! $screen ) {
				return false;
			}

			$screen_id   = isset( $screen->id ) ? (string) $screen->id : '';
			$post_type   = isset( $screen->post_type ) ? (string) $screen->post_type : '';
			$request_uri = $this->get_request_uri();

			$is_legacy_order_screen = 'shop_order' === $post_type && false !== strpos( $request_uri, '/wp-admin/post.php' );
			$is_hpos_order_screen   = false !== strpos( $screen_id, 'wc-orders' ) || false !== strpos( $request_uri, 'page=wc-orders' );

			/**
			 * Filters whether the current admin screen should load the processing confirmation UI.
			 *
			 * @param bool   $is_order_edit_screen Whether current screen is order edit screen.
			 * @param object $screen               Current screen object.
			 */
			return (bool) apply_filters(
				'wc_psc_is_order_edit_screen',
				$is_legacy_order_screen || $is_hpos_order_screen,
				$screen
			);
		}

		/**
		 * Check if current request is a single order save request.
		 *
		 * @return bool
		 */
		private function is_admin_single_order_save_request(): bool {
			if ( ! is_admin() || wp_doing_ajax() ) {
				return false;
			}

			$request_uri = $this->get_request_uri();

			$is_legacy_save = false !== strpos( $request_uri, '/wp-admin/post.php' );
			$is_hpos_save   = false !== strpos( $request_uri, 'page=wc-orders' );

			/**
			 * Filters whether the current request should require processing status confirmation.
			 *
			 * @param bool $is_save_request Whether this is an order save request.
			 */
			return (bool) apply_filters(
				'wc_psc_is_order_save_request',
				$is_legacy_save || $is_hpos_save
			);
		}

		/**
		 * Validate confirmation fields submitted with the order form.
		 *
		 * @return bool
		 */
		private function request_has_valid_confirmation(): bool {
			$confirmed = isset( $_POST[ self::CONFIRMED_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CONFIRMED_FIELD ] ) ) : '';
			$phrase    = isset( $_POST[ self::PHRASE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::PHRASE_FIELD ] ) ) : '';
			$nonce     = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

			if ( '1' !== $confirmed ) {
				return false;
			}

			if ( self::CONFIRMATION_PHRASE !== $phrase ) {
				return false;
			}

			return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
		}

		/**
		 * Check whether status is processing.
		 *
		 * @param string $status Status.
		 *
		 * @return bool
		 */
		private function is_processing_status( string $status ): bool {
			$status = 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;

			return 'processing' === $status;
		}

		/**
		 * Get required capability.
		 *
		 * @return string
		 */
		private function get_required_capability(): string {
			/**
			 * Filters the required capability for confirming processing status changes.
			 *
			 * @param string $capability Required capability.
			 */
			return (string) apply_filters( 'wc_psc_required_capability', 'edit_shop_orders' );
		}

		/**
		 * Queue admin notice for blocked status change.
		 *
		 * @param int $order_id Order ID.
		 *
		 * @return void
		 */
		private function queue_blocked_notice( int $order_id ): void {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				return;
			}

			set_transient(
				self::NOTICE_TRANSIENT_PREFIX . $user_id,
				array(
					'order_id' => $order_id,
				),
				MINUTE_IN_SECONDS
			);
		}

		/**
		 * Render queued admin notice.
		 *
		 * @return void
		 */
		public function render_admin_notice(): void {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				return;
			}

			$key    = self::NOTICE_TRANSIENT_PREFIX . $user_id;
			$notice = get_transient( $key );

			if ( empty( $notice['order_id'] ) ) {
				return;
			}

			delete_transient( $key );

			echo '<div class="notice notice-error is-dismissible"><p>';
			printf(
				/* translators: %d: Order ID. */
				esc_html__( 'برای تغییر وضعیت سفارش #%d به «در حال انجام»، باید ابتدا در پنجره تأیید، عبارت «بله» را وارد کنید.', 'woocommerce-processing-status-confirmation' ),
				absint( $notice['order_id'] )
			);
			echo '</p></div>';
		}

		/**
		 * Get current request URI.
		 *
		 * @return string
		 */
		private function get_request_uri(): string {
			return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		}

		/**
		 * Get inline JavaScript.
		 *
		 * @return string
		 */
		private function get_inline_js(): string {
			return <<<'JS'
(function ($) {
	'use strict';

	if ('undefined' === typeof window.wcPscConfirmation) {
		return;
	}

	var config = window.wcPscConfirmation;
	var confirmed = false;
	var pendingSubmit = false;
	var lastValue = null;
	var suppressChange = false;
	var form = null;
	var statusSelect = null;

	function isTargetStatus(value) {
		var targets = config.settings && config.settings.targetStatuses ? config.settings.targetStatuses : ['wc-processing', 'processing'];

		return targets.indexOf(value) !== -1;
	}

	function getExactPhrase() {
		return config.settings && config.settings.exactPhrase ? config.settings.exactPhrase : 'بله';
	}

	function findForm() {
		var postForm = $('#post');

		if (postForm.length) {
			return postForm.first();
		}

		var orderStatusForm = $('form').has('#order_status, select[name="order_status"]').first();

		if (orderStatusForm.length) {
			return orderStatusForm;
		}

		return $('form').first();
	}

	function findStatusSelect() {
		var selectors = [
			'#order_status',
			'select[name="order_status"]',
			'#post_status',
			'select[name="post_status"]',
			'.wc-order-status select',
			'.woocommerce-order-data select[name="status"]'
		];

		var index;
		var element;

		for (index = 0; index < selectors.length; index++) {
			element = $(selectors[index]);

			if (element.length) {
				return element.first();
			}
		}

		return $();
	}

	function ensureHiddenFields() {
		if (! form || ! form.length) {
			return;
		}

		if (! form.find('#wc_psc_processing_confirmed').length) {
			form.append(
				$('<input>', {
					type: 'hidden',
					id: 'wc_psc_processing_confirmed',
					name: config.fields.confirmed,
					value: '0'
				})
			);
		}

		if (! form.find('#wc_psc_processing_phrase').length) {
			form.append(
				$('<input>', {
					type: 'hidden',
					id: 'wc_psc_processing_phrase',
					name: config.fields.phrase,
					value: ''
				})
			);
		}

		if (! form.find('#wc_psc_processing_confirmation_nonce').length) {
			form.append(
				$('<input>', {
					type: 'hidden',
					id: 'wc_psc_processing_confirmation_nonce',
					name: config.fields.nonce,
					value: config.nonce
				})
			);
		}
	}

	function buildModal() {
		var overlay;
		var dialog;
		var title;
		var message;
		var label;
		var input;
		var actions;
		var cancelButton;
		var confirmButton;
		var error;

		if ($('#wc-psc-confirm-modal').length) {
			return;
		}

		overlay = $('<div>', {
			id: 'wc-psc-confirm-modal',
			role: 'dialog',
			'aria-modal': 'true',
			'aria-label': config.i18n.modalAria || 'Confirmation modal'
		}).css({
			position: 'fixed',
			inset: '0',
			display: 'none',
			alignItems: 'center',
			justifyContent: 'center',
			zIndex: '99999',
			background: 'rgba(32, 228, 254, 0.18)'
		});

		dialog = $('<div>').css({
			background: '#20e4fe',
			color: '#00192a',
			padding: '20px',
			borderRadius: '10px',
			boxShadow: '0 8px 30px rgba(0, 0, 0, 0.25)',
			width: '520px',
			maxWidth: '95%',
			direction: 'rtl',
			textAlign: 'right'
		});

		title = $('<h3>').text(config.i18n.title || 'تأیید تغییر وضعیت').css({
			marginTop: '0'
		});

		message = $('<p>').text(config.i18n.message || '').css({
			lineHeight: '1.8'
		});

		label = $('<label>', {
			for: 'wc-psc-confirm-input'
		}).text(config.i18n.inputLabel || 'عبارت تأیید').css({
			display: 'block',
			fontWeight: '600',
			marginBottom: '6px'
		});

		input = $('<input>', {
			id: 'wc-psc-confirm-input',
			type: 'text',
			autocomplete: 'off'
		}).css({
			width: '100%',
			boxSizing: 'border-box',
			padding: '10px',
			margin: '0 0 10px',
			border: '1px solid rgba(0, 0, 0, 0.12)',
			borderRadius: '6px',
			background: '#fff',
			color: '#000'
		});

		actions = $('<div>').css({
			textAlign: 'left'
		});

		cancelButton = $('<button>', {
			id: 'wc-psc-confirm-cancel',
			type: 'button',
			class: 'button'
		}).text(config.i18n.cancel || 'انصراف');

		confirmButton = $('<button>', {
			id: 'wc-psc-confirm-ok',
			type: 'button',
			class: 'button button-primary'
		}).text(config.i18n.confirm || 'تأیید');

		error = $('<p>', {
			id: 'wc-psc-confirm-error'
		}).text(config.i18n.error || 'Invalid confirmation phrase.').css({
			display: 'none',
			color: '#7b0214',
			marginTop: '8px',
			marginBottom: '0'
		});

		actions.append(cancelButton, ' ', confirmButton);
		dialog.append(title, message, label, input, actions, error);
		overlay.append(dialog);
		$('body').append(overlay);
	}

	function showModal(callback) {
		var modal;

		buildModal();

		modal = $('#wc-psc-confirm-modal');

		$('#wc-psc-confirm-error').hide();
		$('#wc-psc-confirm-input').val('');

		modal.css('display', 'flex');

		window.setTimeout(function () {
			$('#wc-psc-confirm-input').trigger('focus');
		}, 30);

		$('#wc-psc-confirm-cancel').off('click.wcPsc').on('click.wcPsc', function (event) {
			event.preventDefault();
			modal.hide();
			callback(false);
		});

		$('#wc-psc-confirm-ok').off('click.wcPsc').on('click.wcPsc', function (event) {
			var value;

			event.preventDefault();

			value = $.trim($('#wc-psc-confirm-input').val());

			if (value === getExactPhrase()) {
				$('#wc-psc-confirm-error').hide();
				modal.hide();
				callback(true);
				return;
			}

			$('#wc-psc-confirm-error').show();
		});

		$('#wc-psc-confirm-input').off('keydown.wcPsc').on('keydown.wcPsc', function (event) {
			if ('Enter' === event.key) {
				event.preventDefault();
				$('#wc-psc-confirm-ok').trigger('click');
			}

			if ('Escape' === event.key) {
				event.preventDefault();
				$('#wc-psc-confirm-cancel').trigger('click');
			}
		});
	}

	function setConfirmedState(value) {
		ensureHiddenFields();

		$('#wc_psc_processing_confirmed').val(value ? '1' : '0');
		$('#wc_psc_processing_phrase').val(value ? getExactPhrase() : '');

		confirmed = !! value;
	}

	function setStatusValue(value) {
		if (! statusSelect || ! statusSelect.length) {
			return;
		}

		suppressChange = true;
		statusSelect.val(value).trigger('change.select2').trigger('change');
		suppressChange = false;
	}

	function startConfirmationFlow() {
		if (confirmed) {
			return;
		}

		showModal(function (ok) {
			if (! ok) {
				if (statusSelect && statusSelect.length && null !== lastValue) {
					setStatusValue(lastValue);
				}

				pendingSubmit = false;
				return;
			}

			setConfirmedState(true);

			if (statusSelect && statusSelect.length) {
				setStatusValue('wc-processing');
				lastValue = statusSelect.val();
			}

			if (pendingSubmit && form && form.length) {
				pendingSubmit = false;
				form.off('submit.wcPscGuard');
				form.get(0).submit();
			}
		});
	}

	function bindStatusSelect() {
		if (! statusSelect || ! statusSelect.length) {
			return;
		}

		lastValue = statusSelect.val();

		statusSelect.on('change.wcPscGuard select2:select.wcPscGuard', function () {
			var value;

			if (suppressChange) {
				return;
			}

			value = statusSelect.val();

			if (isTargetStatus(value) && ! confirmed) {
				setStatusValue(lastValue);
				startConfirmationFlow();
				return;
			}

			if (! isTargetStatus(value) && confirmed) {
				setConfirmedState(false);
			}

			lastValue = value;
		});
	}

	function bindFormSubmit() {
		if (! form || ! form.length) {
			return;
		}

		form.on('submit.wcPscGuard', function (event) {
			var intendedStatus = statusSelect && statusSelect.length ? statusSelect.val() : null;

			if (intendedStatus && isTargetStatus(intendedStatus) && ! confirmed) {
				event.preventDefault();
				pendingSubmit = true;
				startConfirmationFlow();
				return false;
			}

			return true;
		});
	}

	$(function () {
		form = findForm();
		statusSelect = findStatusSelect();

		if (! form.length) {
			return;
		}

		ensureHiddenFields();
		bindStatusSelect();
		bindFormSubmit();
	});
}(jQuery));
JS;
		}
	}
}

WC_Processing_Status_Confirmation::instance();
