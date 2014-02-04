<?php
if (!$_GET['date']) $_GET['date'] = date("Y-m-d");
$title = date("F j, Y",strtotime($_GET['date']));
require('header.inc.php');

echo "<nav><a href=\"?date=" . date("Y-m-d",strtotime("-1 day",strtotime($_GET['date']))) . "\">previous day</a> | ";
if (strtotime($_GET['date']) >= strtotime(date("Y-m-d",strtotime("now")))) echo "next day";
else echo "<a href=\"?date=" . date("Y-m-d",strtotime("+1 day",strtotime($_GET['date']))) . "\">next day</a>";
echo "</nav>";
 
// get the current leaderboard
$leaderboard_id = get_leaderboard($_GET['date']);
$leaderboard = get_saved_leaderboard($leaderboard_id);
print_leaderboard($leaderboard);

require('footer.inc.php');
?>
