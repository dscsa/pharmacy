jQuery(load)

function load() {
  jQuery('input[name="register"]').click(function(e) {
    console.log('button', this)
  })

  jQuery('form').submit(function(e) {
    console.log('form', this)
    e.preventDefault()
  })
}
