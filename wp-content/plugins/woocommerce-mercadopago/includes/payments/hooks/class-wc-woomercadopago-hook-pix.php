<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @package MercadoPago
 * @category Includes
 * @author Mercado Pago
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Hook_Pix
 */
class WC_WooMercadoPago_Hook_Pix extends WC_WooMercadoPago_Hook_Abstract {

	/**
	 * Load Hooks
	 */
	public function load_hooks() {
		parent::load_hooks();
		if ( ! empty( $this->payment->settings['enabled'] ) && 'yes' === $this->payment->settings['enabled'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'add_checkout_scripts_pix' ) );
			add_action( 'woocommerce_after_checkout_form', array( $this, 'add_mp_settings_script_pix' ) );
			add_action( 'woocommerce_thankyou_' . $this->payment->id, array( $this, 'update_mp_settings_script_pix' ) );
			add_filter( 'woocommerce_gateway_title', array( $this, 'add_badge_new' ), 10, 2 );
		}
	}

	/**
	 *  Add Discount
	 */
	public function add_discount() {
		// @todo need fix Processing form data without nonce verification
		// @codingStandardsIgnoreLine
		if ( ! isset( $_POST['mercadopago_pix'] ) ) {
			return;
		}
		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}
		// @todo need fix Processing form data without nonce verification
		// @codingStandardsIgnoreLine
		$pix_checkout = $_POST['mercadopago_pix'];
		parent::add_discount_abst( $pix_checkout );
	}

	/**
	 * Add Checkout Scripts
	 */
	public function add_checkout_scripts_pix() {
		if ( is_checkout() && $this->payment->is_available() && ! get_query_var( 'order-received' ) ) {

			wp_localize_script(
				'woocommerce-mercadopago-pix-checkout',
				'wc_mercadopago_pix_params',
				array(
					'site_id'             => $this->payment->get_option_mp( '_site_id_v1' ),
					'discount_action_url' => $this->payment->discount_action_url,
					'payer_email'         => esc_js( $this->payment->logged_user_email ),
					'apply'               => __( 'Apply', 'woocommerce-mercadopago' ),
					'remove'              => __( 'Remove', 'woocommerce-mercadopago' ),
					'coupon_empty'        => __( 'Please, inform your coupon code', 'woocommerce-mercadopago' ),
					'choose'              => __( 'To choose', 'woocommerce-mercadopago' ),
					'other_bank'          => __( 'Other bank', 'woocommerce-mercadopago' ),
					'discount_info1'      => __( 'You will save', 'woocommerce-mercadopago' ),
					'discount_info2'      => __( 'with discount of', 'woocommerce-mercadopago' ),
					'discount_info3'      => __( 'Total of your purchase:', 'woocommerce-mercadopago' ),
					'discount_info4'      => __( 'Total of your purchase with discount:', 'woocommerce-mercadopago' ),
					'discount_info5'      => __( '*After payment approval', 'woocommerce-mercadopago' ),
					'discount_info6'      => __( 'Terms and conditions of use', 'woocommerce-mercadopago' ),
					'loading'             => plugins_url( '../../assets/images/', plugin_dir_path( __FILE__ ) ) . 'loading.gif',
					'check'               => plugins_url( '../../assets/images/', plugin_dir_path( __FILE__ ) ) . 'check.png',
					'error'               => plugins_url( '../../assets/images/', plugin_dir_path( __FILE__ ) ) . 'error.png',
				)
			);
		}
	}

	/**
	 * MP Settings Ticket
	 */
	public function add_mp_settings_script_pix() {
		parent::add_mp_settings_script();
	}

	/**
	 * Update settings script pix
	 *
	 * @param string $order_id Order Id.
	 */
	public function update_mp_settings_script_pix( $order_id ) {
		parent::update_mp_settings_script( $order_id );
		$order              = wc_get_order( $order_id );
		$qr_base64          = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( 'mp_pix_qr_base64' ) : get_post_meta( $order->get_id(), 'mp_pix_qr_base64', true );
		$qr_code            = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( 'mp_pix_qr_code' ) : get_post_meta( $order->get_id(), 'mp_pix_qr_code', true );
		$transaction_amount = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( 'mp_transaction_amount' ) : get_post_meta( $order->get_id(), 'mp_transaction_amount', true );
		if ( empty( $qr_base64 ) && empty( $qr_code ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script(
			'woocommerce-mercadopago-pix-order-recived',
			plugins_url( '../../assets/js/pix_mercadopago_order_received' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION,
			false
		);

		$currency_symbol = WC_WooMercadoPago_Configs::get_country_configs();

		$parameters = array(
			'img_pix'             => plugins_url( '../../assets/images/img-pix.png', plugin_dir_path( __FILE__ ) ),
			'amount'              => number_format( $transaction_amount, 2, ',', '.' ),
			'qr_base64'           => $qr_base64,
			'title_purchase_pix'  => __( 'Now you just need to pay with PIX to finalize your purchase', 'woocommerce-mercadopago' ),
			'title_how_to_pay'    => __( 'How to pay with PIX:', 'woocommerce-mercadopago' ),
			'step_one'            => __( 'Go to your bank\'s app or website', 'woocommerce-mercadopago' ),
			'step_two'            => __( 'Search for the option to pay with PIX', 'woocommerce-mercadopago' ),
			'step_three'          => __( 'Scan the QR code or PIX code', 'woocommerce-mercadopago' ),
			'step_four'           => __( 'Done! You will see the payment confirmation', 'woocommerce-mercadopago' ),
			'text_amount'         => __( 'Value: ', 'woocommerce-mercadopago' ),
			'currency'            => $currency_symbol[ $this->payment->get_option_mp( '_site_id_v1' ) ]['currency_symbol'],
			'text_scan_qr'        => __( 'Scan the QR code:', 'woocommerce-mercadopago' ),
			'text_time_qr_one'    => __( 'Code valid for ', 'woocommerce-mercadopago' ),
			'qr_date_expiration'  => $this->payment->get_option_mp( 'checkout_pix_date_expiration', '1' ),
			'text_time_qr_two'    => ( 1 < $this->payment->get_option_mp( 'checkout_pix_date_expiration', '1' ) ? __( ' days', 'woocommerce-mercadopago' ) : __( ' day', 'woocommerce-mercadopago' ) ),
			'text_description_qr' => __( 'If you prefer, you can pay by copying and pasting the following code', 'woocommerce-mercadopago' ),
			'qr_code'             => $qr_code,
			'text_button'         => __( 'Copy code', 'woocommerce-mercadopago' ),
		);

		wc_get_template(
			'order-received/show-pix.php',
			$parameters,
			'woo/mercado/pago/module/',
			WC_WooMercadoPago_Module::get_templates_path()
		);

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'woocommerce-mercadopago-pix-checkout',
			plugins_url( '../../assets/css/basic_checkout_mercadopago' . $suffix . '.css', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION
		);
	}

	/**
	 * Add Badge New
	 *
	 * @param string $title Title.
	 * @param string $id Id.
	 *
	 * @return string
	 */
	public function add_badge_new( $title, $id ) {
		if ( ! preg_match( '/woo-mercado-pago/', $id ) ) {
			return $title;
		}

		if ( $id !== $this->payment->id ) {
			return $title;
		}

		if ( ! is_checkout() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $title;
		}

			$title .= '<small class="mp-pix-checkout-title-badge">' . __( 'New', 'woocommerce-mercadopago' ) . '</small>';

		return $title;
	}
}
