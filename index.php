<?php
require('functions.php');
if (!$_GET['date']) $_GET['date'] = date("Y-m-d");
?>
<!DOCTYPE html>
<html>
<head>
<title>Spelunky: <?php echo date("F j, Y",strtotime($_GET['date'])); ?>!</title>
</head>
<body>
<h1>Spelunky: <?php echo date("F j, Y",strtotime($_GET['date'])); ?>!</h1>
<?php
echo "<p><a href=\"?date=" . date("Y-m-d",strtotime("-1 day",strtotime($_GET['date']))) . "\">previous day</a> | ";
if (strtotime($_GET['date']) >= strtotime(date("Y-m-d",strtotime("now")))) echo "next day";
else echo "<a href=\"?date=" . date("Y-m-d",strtotime("+1 day",strtotime($_GET['date']))) . "\">next day</a>";
echo "</p>";
 
// get the current leaderboard
$leaderboard_id = get_leaderboard($_GET['date']);
$leaderboard = get_saved_leaderboard($leaderboard_id);
print_leaderboard($leaderboard);
?>
</body>
</html>
