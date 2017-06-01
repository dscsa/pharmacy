jQuery(load)

function load() {
  jQuery('form').submit(function(e) {
    jQuery.ajax({
      url:'/account?add-to-cart=281',
      type: 'GET',
      success:function($data) {
        console.log('registering, product added')
      }
    })
    //this._wp_http_referer.value = "/account/orders"

    var firstname = document.createElement('input')
    var lastname  = document.createElement('input')
    firstname.type = lastname.type = 'hidden'
    firstname.name = 'billing_first_name'
    lastname.name  = 'billing_last_name'
    firstname.value = this.sr_firstname.value
    lastname.value = this.sr_lastname.value;
    this.appendChild(firstname)
    this.appendChild(lastname)

    this.username.value = firstname.value+' '+lastname.value
  })
}
