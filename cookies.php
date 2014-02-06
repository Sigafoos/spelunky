<?php
require('functions.php');

//if (!is_logged_in()) login();

//echo new_geeklist();
$geeklist_id = get_geeklist(date("Y-m"));
$leaderboard_id = get_leaderboard(date("Y-m-d"));
if ($leaderboard_id) $leaderboard = get_saved_leaderboard($leaderboard_id);

//echo geeklist_entry($leaderboard,$geeklist_id);
geeklist_entry($leaderboard,$geeklist_id,"3071185");
?>
