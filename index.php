<?php
require('functions.php');
?>
<!DOCTYPE html>
<html>
<head>
<title>Spelunky!</title>
</head>
<body>
<h1>Spelunky!</h1>
<?php
// get the BGGWW members
$members = get_group_members("103582791432200102");

// get the current leaderboard
$leaderboard_id = get_leaderboard();
$leaderboard = get_leaderboard_data($members,$leaderboard_id);

print_leaderboard($leaderboard);
?>
</body>
</html>
