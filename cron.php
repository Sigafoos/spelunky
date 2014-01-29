<?php
require('functions.php');

// get the BGGWW members
$members = get_group_members("103582791432200102");

// get the current leaderboard
$leaderboard_id = get_leaderboard();
$leaderboard = get_leaderboard_data($members,$leaderboard_id);

if (save_leaderboard($leaderboard, $leaderboard_id)) update_leaderboard($leaderboard);
echo "All shiny, cap'n"; // turn this off or pipe the output into /dev/null
?>
