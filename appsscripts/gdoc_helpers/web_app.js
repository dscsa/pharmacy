function testPost() {
  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:'{"method":"watchFiles", "folder":"OLD"}'}}
  debugEmail(event, doPost(event))
}

function doPost(e) {

  try{

    if (e.parameter.GD_KEY != GD_KEY)
      return debugEmail('web_app post wrong password', e)

    if ( ! e.postData || ! e.postData.contents)
      return debugEmail('web_app post not post data', e)

    var contents = JSON.parse(e.postData.contents)

    if (contents.method == 'removeFiles')
      return removeFiles(contents)

    if (contents.method == 'watchFiles')
      return watchFiles(contents)

    if (contents.method == 'newSpreadsheet')
      return newSpreadsheet(contents)

    if (contents.method == 'createCalendarEvent')
      return createCalendarEvent(contents)

    if (contents.method == 'removeCalendarEvents')
      return removeCalendarEvents(contents)

    if (contents.method == 'searchCalendarEvents')
      return searchCalendarEvents(contents)

    if (contents.method == 'modifyCalendarEvents')
      return modifyCalendarEvents(contents)

    debugEmail('web_app post no matching method', e)

  } catch(err){
      debugEmail('web_app post error thrown', err, e)
  }
}
