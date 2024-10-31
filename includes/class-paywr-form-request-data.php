<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( !$order ){
    wp_die('Not isset order data');
    return;
} else if ( !$fields ) {
    wp_die('Not isset order data');
    return;
} else if ( !$paywr_vlogin ) {
    wp_die('Not isset order data');
    return;
}

$site_url = get_bloginfo('wpurl');
if ($fields['testmode'] == 'yes'){
    if (strpos($site_url, 'localhost') !== false) {
       $site_url = 'https://mysite.com/wc-api/paywr';
    } else {
        $site_url = $site_url . '/wc-api/paywr';
    }
} else {
    $site_url = $site_url . '/wc-api/paywr';
}

if ($fields['testmode'] == 'no'){
    $paywr_vlogin = sanitize_text_field(wp_unslash($fields['paywr_vlogin']));
}

$ttl = sanitize_text_field(wp_unslash($fields['time_to_live']));

$trRequest = [
    "vloginId"		=> $paywr_vlogin,
    "passbackParams"=> $order->id,
    "callbackUrl"	=> $site_url,
    "trData"	=> [
        "amount"		=> $this->order->get_total(),
        "currency"		=> $order->data['currency'] ? $order->data['currency'] : 'USD',
        "reasonL1"		=> get_bloginfo( 'name' ) . ", order #" . $order->id
        ],
    "trOptions"	=> [
        "type"			=> 'QR',
        "timeToLive"	=> is_numeric($ttl) == 1
                             && (int)$ttl >= 60
                             && (int)$ttl <= 600
                             ? $ttl
                             : "600"
        ]
];

$return = apply_filters( 'paywr_transaction_data', $trRequest ); // filter

return $return;