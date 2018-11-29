jQuery(load)
function load() {

  signup2signout()
  translate()

  if (window.location.pathname == '/account/details/')
    return account_page()

  if (window.location.pathname == '/account/')
    return account_page()
}

function account_page() {
  console.log('account.js account page')
  upgradeAutofill()
  upgradePharmacy()
  upgradeAllergies()
  upgradeBirthdate()
  clearEmail()
  disableFixedFields()
  setSource()
  savePaymentCard()
}
