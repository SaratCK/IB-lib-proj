// For determining whether a location is open right now (Takes Nick's open hours)
  function isOpen(hours) {
    var currentTime = timeNow();

    var d = new Date();
    var weekday = new Array(7);
    weekday[0]=  "Sunday";
    weekday[1] = "Monday";
    weekday[2] = "Tuesday";
    weekday[3] = "Wednesday";
    weekday[4] = "Thursday";
    weekday[5] = "Friday";
    weekday[6] = "Saturday";
    var today = weekday[d.getDay()];
    var yesterday = weekday[(d.getDay()-1)%6];

    if (!yesterday) {
      yesterday = "Saturday";
    }
    hoursToday = hours[today];
    hoursYesterday = hours[yesterday];
    var todayIsWeird = hoursToday[1] < hoursToday[0]; // weird means hours extend to the next day...
    var yesterdayIsWeird = hoursYesterday[1] < hoursYesterday[0];

    var effectiveHours = null;

    if (currentTime < hoursYesterday[1] && yesterdayIsWeird) {
        effectiveHours = hoursYesterday;
    } else {
        effectiveHours = hoursToday;
    }

    var effectiveWeird = effectiveHours[1] < effectiveHours[0];

    if (effectiveWeird) {
        return currentTime > effectiveHours[0] && currentTime < "23:59:59" || currentTime < effectiveHours[1];
    } else {
        return currentTime > effectiveHours[0] && currentTime < effectiveHours[1];
    }
  }
  function timeNow() {
    var d = new Date();
    var h = (d.getHours()<10?'0':'') + d.getHours();
    var m = (d.getMinutes()<10?'0':'') + d.getMinutes();
    return h + ":" + m + ":" + "00";
  }

  // For displaying open hours (take in Nick's hours object)
  function todaysHoursNicely(hours) {
    if (!hours) {
      return "?";
    }
    var d = new Date();
    var weekday = new Array(7);
    weekday[0]=  "Sunday";
    weekday[1] = "Monday";
    weekday[2] = "Tuesday";
    weekday[3] = "Wednesday";
    weekday[4] = "Thursday";
    weekday[5] = "Friday";
    weekday[6] = "Saturday";
    var today = weekday[d.getDay()];
    hoursToday = hours[today];
    var open = stylizeHour(hoursToday[0]);
    var close = stylizeHour(hoursToday[1]);
    if (hoursToday[0] === hoursToday[1]) {
      return "Closed Today";
    }
    return open + " - " + close;
  }
  function stylizeHour(hour) {
    if (hour == "00:00:00") { return "midnight"; }
    if (hour == "12:00:00") { return "noon"; }
    if (hour > "12:00:00") {
      littleHand = parseInt(hour.slice(0,5), 10) - 12;
      if (hour.slice(3,5) == "00") { return littleHand.toString() + "pm"; }
      return littleHand.toString() + ":" + hour.slice(3,5) + "pm";
    }
    littleHand = parseInt(hour.slice(0,5), 10);
    if (hour.slice(3,5) == "00") { return littleHand.toString() + "am"; }
    return littleHand.toString() + ":" + hour.slice(3,5) + "am";
  }

  function openMessage(hours) {
    if (isOpen(hours)) {
      return 'Open Now';
    }
    return 'Currently Closed';
  }