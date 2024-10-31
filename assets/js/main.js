jQuery(function($){
    
    jQuery('body').on('click', '#paywr-mobile-generate', function(e){
        paywr_qr_code_show();
    });
    
    paywr_qr_code_show();
    
    function paywr_qr_code_show(){
        
        if ( jQuery('#paywr-shop').val() ) {
            var shop = jQuery('#paywr-shop').val();
        } else{
            return;
        }
        
        if ( jQuery('#paywr-code-text').val() ) {
            var trId = jQuery('#paywr-code-text').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-order-amount').val() ) {
            var amount = jQuery('#paywr-order-amount').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-order-currency').val() ) {
            var currency = jQuery('#paywr-order-currency').val();
        } else{
            return;
        }
        
        if ( jQuery('#paywr-ttl').val() ) {
            var ttl = jQuery('#paywr-ttl').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-expired').val() ) {
            var expired = jQuery('#paywr-expired').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-expires-in').val() ) {
            var expires_in = jQuery('#paywr-expires-in').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-declined').val() ) {
            var declined = jQuery('#paywr-declined').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-failed').val() ) {
            var failed = jQuery('#paywr-failed').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-payId').val() ) {
            var payId = jQuery('#paywr-payId').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-total').val() ) {
            var total = jQuery('#paywr-total').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-label').val() ) {
            var label = jQuery('#paywr-label').val();
        } else{
            return;
        }

        if ( jQuery('#paywr-order-id').val() ) {
            var order_id = jQuery('#paywr-order-id').val();
        } else{
            return;
        }

        var expires;
        if (sessionStorage.getItem(trId) === null) {
            expires = +Date.now() + ttl * 1000;
            sessionStorage.setItem(trId, expires.toString());
        }
        if (+sessionStorage.getItem(trId) < +Date.now()) {
        document.getElementById('ttl').innerHTML = expired;
        } else {
            expires = +sessionStorage.getItem(trId);
            var timerId =
                countdown(
                    expires,
                    function(ts) {
                        if (expires > +Date.now() && sessionStorage.getItem(trId + '_' + order_id) == 0) {
                            var min = ts.minutes.toString();
                            var sec = ts.seconds.toString();
                            if (min.length < 2) {min = "0" + min}
                            if (sec.length < 2) {sec = "0" + sec}
                            document.getElementById('ttl').innerHTML = expires_in + " " + min + " : " + sec;
                        } 
                        if (expires <= +Date.now() && sessionStorage.getItem(trId + '_' + order_id) == 0) {
                            document.getElementById('ttl').innerHTML = expired;
                            remove_elements()
                        }
                        if (sessionStorage.getItem(trId + '_' + order_id) == 2) {
                            document.getElementById('ttl').innerHTML = declined;
                            remove_elements()
                        }
                        if (sessionStorage.getItem(trId + '_' + order_id) == 3) {
                            document.getElementById('ttl').innerHTML = failed;
                            remove_elements()
                        }
                    },
                    countdown.MINUTES|countdown.SECONDS
                        );
        }

        jQuery('#shop').html(shop);
        jQuery('#tr-id').html(trId);
        jQuery('#amount').html(amount);
        jQuery('#currency').html(currency);
        jQuery('#payId').html(payId);
        jQuery('#total').html(total);
        jQuery('#label').html(label);
        
        var QRC = qrcodegen.QrCode;
        var segs = qrcodegen.QrSegment.makeSegments(trId);
        var qr = QRC.encodeSegments(segs, QRC.Ecc.QUARTILE, 3, 3, -1, false);
        var svg = qr.toSvgString(4);

        jQuery('#paywr-qrcode').html(svg);
        jQuery('#paywr-qrcode').fadeIn(500);

    }
    
    function remove_elements() {
        jQuery(".text-cl").remove();
        jQuery(".qr_code_img").remove();
        jQuery(".paywr-paynow-btn").remove();
        clearInterval(myLoop);
    }

    function paywr_ajax_getorderpaidstatus() {
        var trId = jQuery('#paywr-code-text').val();
        var order_id = jQuery('#paywr-order-id').val();
        var return_url = jQuery('#paywr-return-url').val();
        jQuery.ajax({
            type: "GET",
            url: paywr_params.paywr_ajax_url,
            data: "action=getorderpaidstatus&order_id=" + order_id,
            success: function(res){
                if ( res == 1 ){
                    location.href = return_url;
                }
                if (res != 0) {
                    clearInterval(myLoop);
                }
                sessionStorage.setItem(trId + '_' + order_id, res);
            }
        });
    } 
    var myLoop ="";
    if ( paywr_params.paywr_is_mobilepage ){
        myLoop = setInterval(paywr_ajax_getorderpaidstatus, 3000);
    }

});