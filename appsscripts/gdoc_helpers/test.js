function testWatch() {
    var folder = DriveApp.getFoldersByName('Published').next()
    var query = 'modifiedDate > "2019-11-19T16:07:49.089Z"'
    var iterator = folder.searchFiles(query)

    console.log(['testWatch', query, iterator.hasNext() ? iterator.next().getUrl() : 'No Files Modified'])
}
/**
 * Test the post capp of the web_app.js
 * @return void
 */
function testPost() {
    var event = {
        parameter: {
            GD_KEY: GD_KEY
        },
        postData: {
            contents: '{"method":"watchFiles", "folder":"Published"}'
        }
    }
    console.log(event, doPost(event));
}
