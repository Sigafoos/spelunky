<?php
require('functions.php');
?>
<!DOCTYPE html>
<html>
<head>
<title>Spelunky!</title>
</head>
<body>
<?php
// get the BGGWW members
$members = get_group_members("103582791432200102");

// get the current leaderboard
$leaderboard_id = get_todays_leaderboard();

$leaderboard = get_leaderboard_data($members,$leaderboard_id);

// IF THERE ARE PEOPLE IN THE GROUP NOT PRESENT, CHECK AGAIN?

echo "<pre>";
print_r($leaderboard);
echo "</pre>";
?>
</body>
</html>
