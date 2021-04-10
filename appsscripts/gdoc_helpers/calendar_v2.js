function createEvent_v2(hours, start, cal_id, title, description) {

    var stopDate = addHours(hours, start)
    var startDate = new Date(start)

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
    var calendar = CalendarApp.getCalendarById(cal_id)
    var events = event_ids.map(function(id) {
            return calendar.getEventById(id)
        })

    for (var i in events) {
        var event = events[i]
        event.title = event.getTitle() + ' Deleted:' + new Date();
        event.setTitle(event.title);

        try { //We're sorry, a server error occurred. Please wait a bit and try again."
            event.deleteEvent();
        } catch (e) {
            Utilities.sleep(5000);
            event.deleteEvent();
        }
    }

    return {
      "results": "success"
    };

  } catch (e) {
    if (e.message == 'The calendar event does not exist, or it has already been deleted') {
       return {
        "results": "success"
      };
    }
       
    return {
      "results": "error",
      "error": e.message
    };
  }
}

