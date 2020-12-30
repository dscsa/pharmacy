/**
 * The main post object
 * @param  {object} e The event that is triggering the post
 * @return {string}   The results of the requested endpoint
 */
function doPost(e) {

    try {
        // Check the password.  This should really be something more secure
        if (e.parameter.GD_KEY != GD_KEY) {
            console.error("gdoc_helper: web_app supplied wrong password", e);
        }

        // Make sure there is post data
        if (!e.postData || !e.postData.contents) {
            console.error("gdoc_helper: web_app missing post data", e)
        }

        var response
        var contents = JSON.parse(e.postData.contents)

        switch(contents.method) {
            case 'removeFiles':
                response = removeFiles(contents);
                break;
            case 'watchFiles':
                response = watchFiles(contents);
                break;
            case 'publishFile':
                response = publishFile(contents);
                break;
            case 'moveFile':
                response = moveFile(contents);
                break;
            case 'newSpreadsheet':
                response = newSpreadsheet(contents);
                break;
            case 'createCalendarEvent':
                response = createCalendarEvent(contents);
                break;
            case 'removeCalendarEvents':
                response = removeCalendarEvents(contents);
                break;
            case 'searchCalendarEvents':
                response = searchCalendarEvents(contents);
                break;
            case 'modifyCalendarEvents':
                response = modifyCalendarEvents(contents);
                break;
            case 'shortLinks':
                response = shortLinks(contents);
                break;
            default:
                console.log("gdoc_helpers: no matching method", [contents.method, contents, e]);
        }

        return ContentService
            .createTextOutput(JSON.stringify(response || 'gdoc_helper had not return value'))
            .setMimeType(ContentService.MimeType.JSON)

    } catch (err) {

        console.error('gdoc_helper:  Error thrown from web_app', err);

        return ContentService
            .createTextOutput(JSON.stringify([err, err.stack]))
            .setMimeType(ContentService.MimeType.JSON)
    }
}
