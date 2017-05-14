jQuery(load)

function load() {
  jQuery('input[name="register"]').click(function(e) {
    console.log('button', this)
  })

  jQuery('form').submit(function(e) {
    console.log('form', this.billing_first_name, this.billing_last_name, this.sr_firstname, this.sr_lastname, this.username)
    e.preventDefault()
  })
}
