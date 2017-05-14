jQuery(load)

function load() {
  jQuery('form').submit(function() {
    console.log('form', this)
  })
}
