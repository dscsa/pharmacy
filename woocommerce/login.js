jQuery(load)
function load() {

  console.log('login page always run since might not have ?gp-login after logout')
  upgradeBirthdate()

  jQuery('form:not([action])').attr('action', location.pathname)

  if ( ~ window.location.search.indexOf('register'))
    return register_page()
}

function register_page() {
  console.log('register page')
  //Can't do this in PHP because button text is also "Register" and html inside buttons is escaped as text
  jQuery('#customer_login h2').html('<div class="english">Get Started (Step 1 of 2)</div><div class="spanish">Registro (Paso 1 de 2)</div>')
  jQuery('#customer_login > div').toggle() //hide login column show registration
  translate()
}
