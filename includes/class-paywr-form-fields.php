<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$return = array(
	'enabled' => array(
		'title'       => __('Enable/Disable', 'payware-mobile-payments-for-woocommerce'),
		'label'       => __('Enable payware mobile payments', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'no'
	),
	'testmode' => array(
		'title'       => __('Sandbox mode', 'payware-mobile-payments-for-woocommerce'),
		'label'       => __('Enable Sandbox mode', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'checkbox',
		'description' => __('Place the payment gateway in test mode.<br>Use the <a href="https://play.google.com/store/apps/details?id=eu.payware.demo.fi">payware e-wallet emulator</a> to confirm a payment.', 'payware-mobile-payments-for-woocommerce'),
		'default'     => 'yes',
		'desc_tip'    => false,
	),
	'title' => array(
		'title'       => __('Title', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'text',
		'description' => __('This controls the title which the user sees during checkout.', 'payware-mobile-payments-for-woocommerce'),
		'default'     => __('Mobile payments', 'payware-mobile-payments-for-woocommerce'),
		'desc_tip'    => false,
	),
	'description' => array(
		'title'       => __('Description', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'textarea',
		'description' => __('This controls the description which the user sees during checkout.', 'payware-mobile-payments-for-woocommerce'),
		'default'     => __('Pay with your favorite payment mobile app.', 'payware-mobile-payments-for-woocommerce'),
	),
	// 'instructions' => array(
	// 	'title'       => __( 'Instructions', 'payware-mobile-payments-for-woocommerce' ),
	// 	'type'        => 'textarea',
	// 	'description' => __( 'Instructions that will be added to the thank you page and emails.', 'payware-mobile-payments-for-woocommerce' ),
	// 	'default'     => __( 'Paid with your favorite payment mobile app.', 'payware-mobile-payments-for-woocommerce' ),
	// 	'desc_tip'    => false,
	// ),

	'[paymet_details]' => array(
		'title'       => __('Payment details', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'title',
	),
	'time_to_live'	 => array(
		'title'		 => __( 'Payment period', 'payware-mobile-payments-for-woocommerce' ),
		'type'       => 'text',
		'description'=> __( 'The time allowed for payment completion in seconds. Accepted range 60 - 600.', 'payware-mobile-payments-for-woocommerce' ),
		'default'	 => __( '600', 'payware-mobile-payments-for-woocommerce' )
	),
	'mobile_page_url' => array(
		'title'       => __('Mobile page url', 'payware-mobile-payments-for-woocommerce'),
		'description' => __('Leave default if unsure: mobile-payments', 'payware-mobile-payments-for-woocommerce'),
		'default'     => 'mobile-payments',
		'desc_tip'    => false,
		'type'        => 'text'
	),

	'partner_data' => array(
		'title'       => __('Partner\'s data (required in production mode)', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'title',
	),
	'partner_id' => array(
		'title'       => __('Partner ID', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'text',
		'description'=> __( '<a href="https://kb.payware.eu/general/faqs#where-is-my-partner-identifier" target="_blank">Where is my payware partner identifier?</a>', 'payware-mobile-payments-for-woocommerce' ),
		'default'	 => __( ' ', 'payware-mobile-payments-for-woocommerce' )
	),
	'paywr_vlogin'	 => array(
		'title'		 => __( 'webPOS identifier', 'payware-mobile-payments-for-woocommerce' ),
		'type'		 => 'text',
		'description'=> __( '<a href="https://kb.payware.eu/general/faqs#where-are-my-virtual-pos-identifiers" target="_blank">Where is my webPOS identifier?</a>', 'payware-mobile-payments-for-woocommerce' ),
		'default'	 => __( ' ', 'payware-mobile-payments-for-woocommerce' )
		),

	'live_payware_public_key' => array(
		'title'       => __('payware Public Key', 'payware-mobile-payments-for-woocommerce'),
		'type'        => 'textarea',
		'description'=> __( '<a href="https://kb.payware.eu/general/faqs#where-is-payware-public-key" target="_blank">Where is payware Public Key?</a>', 'payware-mobile-payments-for-woocommerce' ),
	),
);

$return = apply_filters( 'paywr_form_fields', $return ); // filter

return $return;

