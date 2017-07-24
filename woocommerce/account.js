jQuery(load)
function load() {

  if (window.location.pathname == '/account/details/')
    return account_page()

  if (window.location.pathname == '/account/')
    return account_page()
}

function account_page() {
  console.log('account.js account page')
  signup2signout()
  upgradePharmacy()
  upgradeAllergies()
  disableFixedFields()
  setSource()
}
