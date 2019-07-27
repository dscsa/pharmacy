jQuery(load)
function load() {

  console.log('login page always run since might not have ?gp-login after logout')
  upgradeBirthdate()

  if ( ~ window.location.search.indexOf('register'))
    register_page()

  if (window.sessionStorage)
    upgradePharmacy() //not needed on this page but fetch && cache the results for a quicker checkout page
}

function register_page() {
  console.log('register page')
  //Can't do this in PHP because button text is also "Register" and html inside buttons is escaped as text
  jQuery('#customer_login h2').html('<div class="english">Get Started (Step 1 of 2)</div><div class="spanish">Registro (Paso 1 de 2)</div>')
  jQuery('#customer_login > div').toggle() //hide login column show registration
  jQuery('#username_verification')


  clearEmail() //just in case a registration reloads page with the default email populated
  translate()

  jQuery('#first_name').keyup(function () {
    jQuery('#verify_first_name').text($(this).val());
  })

  jQuery('#last_name').keyup(function () {
    jQuery('#verify_last_name').text($(this).val());
  })

  jQuery('#birth_date_register').keyup(function () {
    jQuery('#verify_birth_date').text($(this).val());
  })
}
