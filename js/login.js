jQuery(load)
function load() {

  console.log('login page always run since might not have ?gp-login after logout')
  upgradeBirthdate()

  if ( ~ window.location.search.indexOf('register'))
    register_page()

  if (window.sessionStorage)
    upgradePharmacy() //not needed on this page but fetch && cache the results for a quicker checkout page

  jQuery('#first_name_login, #first_name_register').on("change keyup paste", onChange)
  jQuery('#last_name_login, #last_name_register').on("change keyup paste", onChange)
  jQuery('#birth_date_login, #birth_date_register').on("change keyup paste", onChange)

  function onChange() {
    var el  = jQuery(this)
    var id  = el.attr('id')
    var val = el.val()

    if (id.slice(0, 10) == 'birth_date')
      val = 'born on '+new Date(val).toUTCString().slice(5, 16)

    console.log(id+' Key Up', val)
    jQuery('#verify_'+id).text(val);
  }
}

function register_page() {
  console.log('register page')
  //Can't do this in PHP because button text is also "Register" and html inside buttons is escaped as text
  jQuery('#customer_login h2').html('<div class="english">Get Started (Step 1 of 2)</div><div class="spanish">Registro (Paso 1 de 2)</div>')
  jQuery('#customer_login > div').toggle() //hide login column show registration

  clearEmail() //just in case a registration reloads page with the default email populated
  translate()
}
