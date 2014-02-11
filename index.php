<?php
if (!$_GET['date']) {
	if (date("G") < 19) $_GET['date'] = date("Y-m-d");
	else $_GET['date'] = date("Y-m-d",strtotime("tomorrow"));
}
$title = date("F j, Y",strtotime($_GET['date']));
require('header.inc.php');

echo "<nav><a href=\"" . date("/Y/m/d/",strtotime("-1 day",strtotime($_GET['date']))) . "\">previous day</a> | ";
// if it's before today, or it's today AND after 7 pm
if ((strtotime($_GET['date']) < strtotime(date("Y-m-d",strtotime("now")))) || (strtotime(date("Y-m-d")) == strtotime($_GET['date']) && date("G") > 18)) echo "<a href=\"" . date("/Y/m/d/",strtotime("+1 day",strtotime($_GET['date']))) . "\">next day</a>";
else echo "next day";
echo "</nav>";
 
// get the current leaderboard
$lb = new Leaderboard($_GET['date']);
$lb->display();

require('footer.inc.php');
?>
