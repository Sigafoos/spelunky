<?php
require('functions.php');

// get the BGGWW members
$members = get_group_members("103582791432200102");

for ($i = 1; $i <= 31; $i++) {
	// get the current leaderboard
	$leaderboard_id = get_leaderboard(date("n") . "/" . $i . "/" . date("Y"));
	$leaderboard = get_leaderboard_data($members,$leaderboard_id);

	if (save_leaderboard($leaderboard, $leaderboard_id)) update_leaderboard($leaderboard);
}
echo "All shiny, cap'n"; // turn this off or pipe the output into /dev/null
?>
