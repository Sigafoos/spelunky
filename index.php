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
// get the current leaderboard
$leaderboard_id = get_leaderboard();
$leaderboard = get_saved_leaderboard($leaderboard_id);
print_leaderboard($leaderboard);
?>
</body>
</html>
