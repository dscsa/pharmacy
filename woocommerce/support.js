jQuery(load)
function load() {

  $('#wpas_call-type').on("select2:selecting", function(e) {
     var map = {
      'General Info':'info',
      'Rx Info':'rx-info',
      'Inventory': 'inventory',
      'Registration':'registration',
      'Delivery Issue':'delivery-issue',
      'Cancel/Delay Order':'cancel-order',
      'Refill Request': 'refill-request',
      'Transfer Request':'transfer-request',
      'Payment': 'payment'
     }

     console.log(e)
  });
