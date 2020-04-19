function testPost() {
  var event = {parameter:{GD_KEY:GD_KEY}, postData:{contents:'{"method":"watchFiles", "folder":"Published"}'}}
  debugEmail(event, doPost(event))
}

function doPost(e) {

  try{

    if (e.parameter.GD_KEY != GD_KEY)
      return debugEmail('web_app post wrong password', e)

    if ( ! e.postData || ! e.postData.contents)
      return debugEmail('web_app post not post data', e)

    var response
    var contents = JSON.parse(e.postData.contents)

    if (contents.method == 'removeFiles')
      response = removeFiles(contents)

    else if (contents.method == 'watchFiles')
      response = watchFiles(contents)

    else if (contents.method == 'publishFile')
      response = publishFile(contents)

    else if (contents.method == 'newSpreadsheet')
      response = newSpreadsheet(contents)

    else if (contents.method == 'createCalendarEvent')
      response = createCalendarEvent(contents)

    else if (contents.method == 'removeCalendarEvents')
      response = removeCalendarEvents(contents)

    else if (contents.method == 'searchCalendarEvents')
      response = searchCalendarEvents(contents)

    else if (contents.method == 'modifyCalendarEvents')
      response = modifyCalendarEvents(contents)

    else if (contents.method == 'shortLinks')
      response = shortLinks(contents)

    else if (contents.method == 'moveFile')
      response = moveFile(contents)

    else
      debugEmail('web_app post no matching method', [contents.method, contents, e])

    return ContentService
      .createTextOutput(JSON.stringify(response || 'gdoc_helper had not return value'))
      .setMimeType(ContentService.MimeType.JSON)

  } catch(err){

    debugEmail('web_app post error thrown', err, e)

    return ContentService
      .createTextOutput(JSON.stringify([err, err.stack]))
      .setMimeType(ContentService.MimeType.JSON)
  }
}
