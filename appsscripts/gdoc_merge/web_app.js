function testMergeInvoice() {

  var contents = JSON.stringify({
    method:'mergeDoc',
    template:'Invoice Template v1',
    file:'TEST Invoice #'+test_order[0]['invoice_number'],
    folder: 'Pending',
    order:test_order
  })

  Logger.log(contents)

  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:contents}}

  debugEmail('testMergeInvoice', event, doPost(event))
}

function testMergeInvoice2() {

  var contents = JSON.stringify({
    method:'mergeDoc',
    template:'Invoice Template v1',
    file:'TEST Invoice #'+test_order3[0]['invoice_number'],
    folder: 'Pending',
    order:test_order3
  })

  Logger.log(contents)

  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:contents}}

  debugEmail('testMergeInvoice2', event, doPost(event))
}


function testMergeFax() {

   var contents = JSON.stringify({
    method:'mergeDoc',
    template:'Transfer Out Fax v1',
    file:'TEST Transfer #'+test_order[0]['pharmacy_phone'],
    folder: 'Test Transfers',
    order:test_order
  })

  Logger.log(contents)

  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:contents}}

  debugEmail(event, doPost(event))
}

/**
 * Handle a post request
 * @param  {object} e The data from the request
 * @return {ContentService}   A formed JSON respons
 */
function doPost(e) {
  try{

    if (e.parameter.GD_KEY != GD_KEY)
      return debugEmail('gdoc_merge post wrong password', e)

    if ( ! e.postData || ! e.postData.contents)
      return debugEmail('gdoc_merge post not post data', e)

    var response
    var contents = JSON.parse(e.postData.contents)

    if (contents.method.includes('v2')) {
        response = v2_routes(contents);
    } else {
        response = v1_routes(contents);
    }

    return ContentService
        .createTextOutput(JSON.stringify(response || 'gdoc_merge has not returned data'))
        .setMimeType(ContentService.MimeType.JSON)

  } catch(err){
      debugEmail('gdoc_merge post error thrown', err, err.errorMessage, err.scriptStackTraceElements, e)
  }
}

/**
 * Handle any routes that have a v2/ in them
 * @param  {object} contents All data that was sent to the request
 * @return {object}          Date to represent the results
 */
function v2_routes(contents) {
    switch (contents.method) {
        case 'v2/createInvoice':
            return createInvoice_v2(contents.templateId, contents.folderId, contents.fileName);
        case 'v2/completeInvoice':
            return completeInvoice_v2(contents.fileId, contents.orderData);
        default:
            console.log('Could not find a match in v2 route', contents);
    }
}

/**
 * handle any older v1 routes
 * @param  {object} contents All data that was sent to the request
 * @return {object}          Data to represent the results
 */
function v1_routes(contents) {
    if (contents.method == 'mergeDoc') {
      return mergeDoc(contents);
    }
}
