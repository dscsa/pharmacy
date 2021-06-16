
//searchAndRemoveEvents_v2

function test() {
  console.log('Hello');
  Logger.log('Goodbye');
}

function TestSearchAndDeleteEvents_v2() {
  console.log(
    searchAndDeleteEvents_v2(
      'c_81mh8qu3m5v2qiolgklue62nvk@group.calendar.google.com',
      'Test Event',
      2160,
      null
    )
  )
}

function testCreateEvent() {
  console.log(createEvent_v2(0.25, "2021-04-23T09:27:04", "support@goodpill.org", "11111 Ben Brown Test Event", []))
}
/**
 * 
 */
function searchAndDeleteEvents_v2(cal_id, word_search, hours, regex_search) {
   // Get the results 
  var search_results = searchEvents_v2(cal_id, word_search, hours, regex_search);

  // Filter down to just ids
  var search_match_ids = search_results.data.map(function(match) { return match.id});

  console.log("Deleting Events: ", search_match_ids);

  // remove any matches
  return removeEvents_v2(cal_id, search_match_ids);
}

/**
 * Search events and find matches
 * 
 *  NOTE: this doesn't include start or past because those features didn't work 
 *  in the old function.  instead of bringing them over, I just left them out
 * 
 * string cal_id       The google id for the calendar to search
 * string word_search  The pattern to use in the search
 * int    hours        How far into the future shoudl we search
 * string regex_search A regex to add to the search to further filter results
 * 
 * return object 
 */
function searchEvents_v2(cal_id, word_search, hours, regex_search) {

    var calendar = CalendarApp.getCalendarById(cal_id)

    if (!calendar) {
      console.error(
        'Could not find calendar: ',
        cal_id
      );
      return {
        "results": "error",
        "message": "Calendar Not Found",
        "calender": cal_id,
      };
    }

    var start = new Date();

    //stop date seems to be required by Google.  Everything should happen within 90 days
    var stop = addHours(hours, start) 

    var config = {
        search: word_search
    }

    // Can't put in name because can't google cal doesn't seem to support a partial word search e.g, 
    // "greg" will not show results for gregory
    var events = calendar.getEvents(start, stop, config) 

    var matches = []
    var haystacks = []

    if (regex_search) {
        var slash = regex_search.lastIndexOf("/")
        var regex = RegExp(regex_search.slice(1, slash), regex_search.slice(slash + 1))
    }

    for (var i in events) {

        var event = {
            id: events[i].getId(),
            title: events[i].getTitle(),
            description: events[i].getDescription(),
            start: events[i].getStartTime(),
            end: events[i].getStartTime()
        }

        console.log(start)
        // Skip events that have been proccessed already
        if (
              ~event.title.indexOf('CALLED') 
              || ~event.title.indexOf('EMAILED') 
              || ~event.title.indexOf('TEXTED')
            ) continue;

        haystacks.unshift((event.title + ' ' + event.description).toLowerCase())

        if (!regex || haystacks[0].match(regex))
            matches.push(event)
    }
    return {
        "results": "success",
        "data": matches
      };
}

function createEvent_v2(hours, start, cal_id, title, description) {
    var startDate = new Date(start)
    
    // We shouldn't create events in the past.
    if (startDate < new Date()) {
      startDate = new Date();
      // set to 30 minutes from now just incase something needs to modify it
      startDate.setMinutes(startDate.getMinutes() + 30); 
      console.log('The requested date is in the past, so using now instead', startDate);
    }

    // Set the ending time
    var stopDate = new Date(startDate.getTime());
    stopDate.setMinutes(stopDate.getMinutes() + (hours * 60));
    console.log("Event Start: ", startDate, ", Event ends: ", stopDate);
    
    var calendar = CalendarApp.getCalendarById(cal_id)

    if (!calendar) {
      console.error(
        'Could not find calendar: ',
        cal_id
      );
      return {
        "results": "error",
        "message": "Calendar Not Found",
        "fileId": cal_id,
      };
    }

    if (! description) {
      console.error(
        'Trying to create a calendar event with an empty array : ',
        title,
        startDate,
        stopDate,
        JSON.stringify(description, null, '  ')
      );
    }

    try {
      calendar
        .createEvent(
            title,
            startDate,
            stopDate, {
                description: JSON.stringify(description, null, '  ')
            }
        );

        return {
          "results": "success"
        };
    } catch (e) {
       return {
            "results": "error",
            "message": "File not found.",
            "error": e.message
        };
    }
}

function removeEvents_v2(cal_id, event_ids) {
  try {
    if (cal_id) {
      var calendar = CalendarApp.getCalendarById(cal_id)
      var events = event_ids.map(function(id) {
              return calendar.getEventById(id)
          })

      for (var i in events) {
          var event = events[i]
          console.log("deleting event", event)
          var title = event.getTitle() + ' Deleted:' + new Date();
          event.setTitle(title);

          try { //We're sorry, a server error occurred. Please wait a bit and try again."
              event.deleteEvent();
          } catch (e) {
              Utilities.sleep(5000);
              event.deleteEvent();
          }
      }
    }
    return {
      "results": "success"
    };

  } catch (e) {
    if ( ~ e.message.indexOf('event does not exist')) {
      return {
        "results": "success",
        "data" : event_ids
      };
    }
       
    return {
      "results": "error",
      "error": e.message,
      "cal_id" : cal_id      
    };
  }
}

