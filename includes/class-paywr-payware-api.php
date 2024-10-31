<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \paywr\JWT\JWT;
class PAYWR_payware_API{
    
    function __construct($data){
        $this->clientId     = trim( $data['paywr_partner_id'] );
        $this->paywr_public_key    = trim( $data['paywr_public_key'] );
        $order              = isset( $data['order'] ) ? $data['order'] : false;
        $fields             = isset( $data['fields'] ) ? $data['fields'] : false;
        $paywr_vlogin             = isset( $data['paywr_vlogin'] ) ? $data['paywr_vlogin'] : false;
        $this->order        = isset( $data['order'] ) ? $data['order'] : false;
        $this->testmode     = $data['testmode'];
        $this->payware_url  = $this->testmode ? "https://sandbox.payware.eu" : "https://api.payware.eu";

        if ( $order ) {
            $this->transaction_data = require_once dirname( __FILE__ ) . '/class-paywr-form-request-data.php';
        }

    }

    public function paywr_get_payment_link(){

        $data_json = $this->paywr_getJsonData();
        $result_create = $this->paywr_get_transaction_id($data_json);

        if ( isset($result_create['status']) && $result_create['status'] == 'error' ) {
            return $result_create;
        } else if ( ! $result_create ) {
            return 'Not found result_create';
        }

        return $result_create;

    }

    // Get transaction id from payware servers
    public function paywr_get_transaction_id($data){

        $result = $this->paywr_client('post', $this->payware_url . '/internal/transactions', $data);
        $result = $result['result'];

        return $result;

    }

    // Callback validation
    public function paywr_callback_validation(){

        $headers    = array_change_key_case(getallheaders(), CASE_UPPER);
        $jwt        = substr($headers['AUTHORIZATION'], 7); // removing leading 'Bearer ' string from JWT

        try {
            $decoded = JWT::decode($jwt, $this->paywr_public_key, array('RS256'));
        } catch (Exception $e) {
            $errorMsg = sanitize_text_field($e->getMessage());
            echo 'Caught exception: ',   esc_attr($errorMsg), "\n";
            paywrLog('Caught exception: ',  $errorMsg);
            return 'FAILURE';
        }

        $tks = \explode('.', $jwt);
        list($headb64, $bodyb64) = $tks;
        $header = JWT::jsonDecode(JWT::urlsafeB64Decode($headb64));
        $claims = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64));

        $jwt_content = array(
            "verify" => 'SUCCESS',
            "header" => (array)$header,
            "claims" => (array)$claims,
        );

        return $jwt_content;
    }

    // payware API client function
    public function paywr_client($method, $where, $data = false){

        $request_data = [
            'headers' => [
                'Content-Type'          => 'application/json;',
                'Accept'                => 'application/json;',
                'Content-Length'        => strlen($data)
            ],
            'method' => strtoupper($method)
        ];

        if ( $data ) $request_data['body'] = $data;

        $response = wp_remote_request( $where, $request_data );

        if ( is_wp_error($response) ) return [
            'code'   => 400,
            'result' => false
        ]; else return [
            'code'   => 200,
            'result' => $response['body']
        ];
    }

    public function paywr_getJsonData(){
        return $this->transaction_data ? json_encode($this->transaction_data, 1) : false;
    }

}