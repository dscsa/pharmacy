jQuery(load)

function load() {
  jQuery('input[name="register"]').click(function() {
    console.log('form', this)
  })
}
