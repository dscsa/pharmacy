/**
 * Test the file watcher to see if it gets file matechs
 * @return {void}
 */
function testWatch() {
    var folder = DriveApp.getFoldersByName('Published').next()
    var query = 'modifiedDate > "2019-11-19T16:07:49.089Z"'
    var iterator = folder.searchFiles(query)

    console.log(['testWatch', query, iterator.hasNext() ? iterator.next().getUrl() : 'No Files Modified'])
}

/**
 * Test the post capp of the web_app.js
 * @return {void}
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

function testFileTasks() {
    console.log("Test File Tasks");
    // Create test file
    var testFile = DriveApp.createFile('Test Suite Test File', 'Hello, world!');
    console.log("Test File Tasks - Test File Created: ", {
        testFileId: testFile.getId()
    });
    testPublishFile(testFile.getId());
    testDeleteFile(testFile.getId());
    // Move test file

    // Publish Test file
    // Trash Test file

    // Clean up after the test.
    if (!testFile.isTrashed()) {
        testFile.setTrashed(true);
    }
}

/**
 * Test the post capp of the web_app.js
 * @return {void}
 */
function testDeleteFile(fileId) {
    var event = {
        parameter: {
            GD_KEY: GD_KEY
        },
        postData: {
            contents: JSON.stringify({
                "method": "v2/removeFile",
                "fileId": fileId
            })
        }
    }

    console.log("Test File Tasks - Remove File Event: ", event);
    console.log("Test File Tasks - Remove Results: ", event, doPost(event));
}

/**
 * Test the post capp of the web_app.js
 * @return {void}
 */
function testPublishFile(fileId) {
    var event = {
        parameter: {
            GD_KEY: GD_KEY
        },
        postData: {
            contents: JSON.stringify({
                "method": "v2/publishFile",
                "fileId": fileId
            })
        }
    }

    console.log("Test File Tasks - Publish File Event: ", event);
    console.log("Test File Tasks - Publish Results: ", event, doPost(event));
}
