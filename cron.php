<?php
require('functions.php');

// get the BGGWW members
$members = get_group_members("103582791432200102");

// get the current leaderboard
// if it's after 9 EST, stop
if (date("G") < 20) {
	$leaderboard_id = get_leaderboard();
	$leaderboard = get_leaderboard_data($members,$leaderboard_id);
	save_leaderboard($leaderboard, $leaderboard_id);
}

// if it's after 7 pm EST
if (date("G") > 18) {
	$leaderboard_id = get_leaderboard(date("m/d/Y",strtotime("tomorrow")));
	$leaderboard = get_leaderboard_data($members,$leaderboard_id);
	save_leaderboard($leaderboard, $leaderboard_id);
}
echo "All shiny, cap'n"; // turn this off or pipe the output into /dev/null
?>
