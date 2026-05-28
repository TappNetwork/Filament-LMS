var COURSE_PACKAGE_VERSION = "b2JkLCeO";
var RESET_LEARNER_DATA = false;

function isTrue(val) {
  return val && String(val).toLowerCase() === 'true';
}

function verifySuspendDataVersion(maxAttempts) {
  var commitDataAndBook;
  var sentBookCheck;
  var sentDataCheck;
  var suspendData = GetDataChunk();
  var suspendDataCPV;

  if (!suspendData) {
    return;
  }

  if (maxAttempts === 0) {
    WriteToDebug('WARNING: ERROR WITH CLEARING SUSPEND OR BOOKMARKING DATA!');
    WriteToDebug('NO MORE ATTEMPTS LEFT. COURSE BEHAVIOR MAY BE NEGATIVELY IMPACTED.');
    return;
  }

  try {
    suspendData = JSON.parse(suspendData);
    suspendDataCPV = suspendData.cpv;
  } catch (e) {
    WriteToDebug('WARNING: Issue parsing suspend data for CPV. Version reset behavior disabled.');
    return;
  }

  if (suspendDataCPV !== COURSE_PACKAGE_VERSION) {
    WriteToDebug('WARNING: Suspend data version mismatch against package version.');
    if (RESET_LEARNER_DATA) {

      if (REVIEW_MODE_IS_READ_ONLY && GetLessonMode() === 3) {
        WriteToDebug('WARNING: Course is in review mode. Unable to clear suspend and bookmarking data!');
        WriteToDebug('Resetting of learner data will not occur. Course behavior may be negatively impacted.');
        return;
      }

      WriteToDebug('Okay to clear data. Now clearing suspend and bookmarking data...');

      sentDataCheck = SetDataChunk("");
      WriteToDebug('Suspend Data Cleared: ' + sentDataCheck );

      sentBookCheck = SetBookmark("");
      WriteToDebug('Bookmark Data Cleared: ' + sentBookCheck );

      commitDataAndBook = CommitData();
      WriteToDebug('Data Commited: ' + commitDataAndBook );

      if (isTrue(sentDataCheck) && isTrue(sentBookCheck) && isTrue(commitDataAndBook)) {
        WriteToDebug('Verified that all bookmarking and suspend data have been cleared. Data commited successfully.');
      } else {
        WriteToDebug('WARNING: ERROR WITH CLEARING SUSPEND OR BOOKMARKING DATA! RETRY ATTEMPTS LEFT: ' + (maxAttempts-1));
        verifySuspendDataVersion(maxAttempts-1);
      }
    } else {
      WriteToDebug('WARNING: Course reset flag not turned on. Resetting of learner data will not occur.');
      WriteToDebug('Course behavior may be negatively impacted.');
    }
  }
}

function verifyForcedCommitTime() {
  if (FORCED_COMMIT_TIME >= 5000) {
    WriteToDebug('Starting Forced Commit heartbeat...');
    var intervalID = setInterval(commitHeartbeat, FORCED_COMMIT_TIME);
  } else {
    WriteToDebug('Forced Commit Time cannot be lower than 5000 milliseconds. Not using forced commit time.');
  }
}

function commitHeartbeat() {
  WriteToDebug('Forced Commit heartbeat triggered. Now saving current session time...');
  SetSessionTime(GetSessionAccumulatedTime());

  WriteToDebug('Forced Commit heartbeat trigger is now triggering a commit...');
  CommitData();
}

function getCourseTitle() {
  return 'A Quick Guide to the Completion of the MDA'
}
