<?php
require('functions.php');

// get the current leaderboard
// if it's after 9 EST, stop
if (date("G") < 21) {
	$lb = new Leaderboard();
	$lb->update();

	// at 7 post the full results
	if (date("G") == 19) $lb->update_geeklist();
}

// if it's after 7 pm EST
if (date("G") > 18) {
	$lb = new Leaderboard("tomorrow");
	$lb->update();
}
echo "All shiny, cap'n\n"; // turn this off or pipe the output into /dev/null
?>
