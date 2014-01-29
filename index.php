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
$leaderboard_id = get_todays_leaderboard();

$leaderboard = get_leaderboard_data($members,$leaderboard_id);

// IF THERE ARE PEOPLE IN THE GROUP NOT PRESENT, CHECK AGAIN?

// you don't get my db info!
require('../db_connect.php');
$query = "SELECT steamid, name, avatar, updated FROM spelunky_players";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
	$database[$row['steamid']]['name'] = $row['name'];
	$database[$row['steamid']]['avatar'] = $row['avatar'];
	$database[$row['steamid']]['updated'] = $row['updated'];
}

foreach ($leaderboard as $steamid=>&$entry) {
	if (!$database[$steamid]) { // also deal with if it was last updated X days ago
		echo "<p>" . $steamid . " is not in the database yet</p>";

		$info = get_player_info($steamid);
		update_player($info);

		$database[$steamid]['name'] = $info['name'];
		$database[$steamid]['avatar'] = $info['avatar'];
	}
		$entry['name'] = $database[$steamid]['name'];
		$entry['avatar'] = $database[$steamid]['avatar'];
}

print_leaderboard($leaderboard);

?>
</body>
</html>
