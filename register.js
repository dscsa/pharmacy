jQuery(load)

function load() {
  jQuery('form').submit(function(e) {
    jQuery.ajax({
      url:'/account?add-to-cart=281',
      type: 'GET',
      success:function($data) {
        console.log('registering, add product', $data)
      }
    })
    this._wp_http_referer.value = "/account/orders"
    this.sr_firstname.value = this.billing_first_name.value
    this.sr_lastname.value  = this.billing_last_name.value
    this.username.value     = this.sr_firstname.value+' '+this.sr_lastname.value
  })
}
