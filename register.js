jQuery(load)

function load() {
  jQuery('form').attr('action', '/account/orders').submit(function(e) {
    this.sr_firstname.value = this.billing_first_name.value
    this.sr_lastname.value  = this.billing_last_name.value
    this.username.value     = this.sr_firstname.value+' '+this.sr_lastname.value
  })
}
