// version: 7.12.0.a.1.5.1
// sha: 4770bd49d79a0e9175bc7d4962e8ed15628c37a2
function SetBookmark(){var o=window.parent,t=window.location.href;o.SetBookmark(t.substring(t.toLowerCase().lastIndexOf("/scormcontent/")+14,t.length),document.title),o.CommitData()}SetBookmark();