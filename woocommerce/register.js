jQuery(load)
function load() {
  console.log('common.js loaded', window.location.pathname, window.location.search)
  if (window.location.search == '?register')
    return register_page()
}

function register_page() {
  console.log('common.js register page')
  translate()
  createUsername()
  upgradeBirthdate()
  setSource()
}
