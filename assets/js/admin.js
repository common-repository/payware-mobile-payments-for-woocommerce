jQuery(document).ready(function(){
    var content = '<div class=\"paywr-notice-admin-btns\"> ' +
        '<a target="_blank" class=\"paywr-btn-site\" href=\"https://payware.eu\">'+paywr_l18n['Visit site']+'</a>' +
        '</div>';
    jQuery('h2').after( content );
});