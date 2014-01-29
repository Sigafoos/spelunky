<?php
// pretty much everything useful here was stolen from the KlepekVsRemo repository:
// https://github.com/amarriner/KlepekVsRemo/

// you don't get my db info!
require('/var/www/db_connect.php');

// Checks to see if the player's data has already been tweeted
// this is not at all necessary
function check_today($player) {
	$found = 1;

	$last = trim(file_get_contents(_PWD . '/' . $player->steamid));
	if ($last != date('m/d/Y')) {
		$found = 0;
	}

	return $found;
}

// Retrieves the players' leaderboard data for today
function get_leaderboard_data($members, $leaderboard_id) {
	global $db;
	$score = -1;
	$scores = array();

	// using my id, then grabbing the scores for anyone in the group
	$player_spelunky_leaderboard = 'http://steamcommunity.com/stats/239350/leaderboards/' . $leaderboard_id . '/?xml=1&steamid=76561198000338942';
	$xml = file_get_contents($player_spelunky_leaderboard);
	$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($ob);
	$array = json_decode($json, true);

	if (!$array['entries']) return NULL;

	// get the player info
	$query = "SELECT steamid, name, avatar, updated FROM spelunky_players";
	$result = $db->query($query);
	while ($row = $result->fetch_assoc()) {
		$database[$row['steamid']]['name'] = $row['name'];
		$database[$row['steamid']]['avatar'] = $row['avatar'];
		$database[$row['steamid']]['updated'] = $row['updated'];
	}

	foreach($array['entries']['entry'] as $key => $value) {
		if (in_array($value['steamid'],$members)) {
			$scores[$value['steamid']]['score'] = $value['score'];

			// Characters are stored as hex values, converting to decimal
			$scores[$value['steamid']]['character'] = hexdec(substr($value['details'], 0, 2));

			// Levels ares stored as hex values from 0-19, so convert them into what's shown on the leaderboards in-game
			$level = hexdec(substr($value['details'], 8, 2));
			$scores[$value['steamid']]['level'] = ceil($level / 4) . "-" . ($level % 4 == 0? 4 : ($level % 4));

			// do we know you?
			if (!$database[$value['steamid']]) { // also deal with if it was last updated X days ago
				$info = get_player_info($value['steamid']);
				update_player($info);

				$database[$value['steamid']]['name'] = $info['name'];
				$database[$value['steamid']]['avatar'] = $info['avatar'];
			}

			$scores[$value['steamid']]['name'] = $database[$value['steamid']]['name'];
			$scores[$value['steamid']]['avatar'] = $database[$value['steamid']]['avatar'];
		}
	}


	// IF THERE ARE PEOPLE IN THE GROUP NOT PRESENT, CHECK AGAIN?
	return $scores;
}

// Find the id for the daily challenge leaderboard. Defaults to today.
function get_leaderboard($date = FALSE) {
	global $db;

	if (!$date) $date = date('m/d/Y');
	else $date = date("m/d/Y",strtotime($date));
	$today = date("m/d/Y",strtotime($date)) . ' DAILY';
	$leaderboard = '';

	$url = 'http://steamcommunity.com/stats/239350/leaderboards/?xml=1';

	$xml = file_get_contents($url);
	$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($ob);
	$array = json_decode($json, true);

	foreach($array['leaderboard'] as $key => $value) {
		if ($value['name'] == $today) {
			$leaderboard = $value['lbid'];
		}
	}

	if ($leaderboard) {
		$query = "SELECT date FROM spelunky_games WHERE leaderboard_id=" . $leaderboard;
		$result = $db->query($query);
		$row = $result->fetch_assoc();

		// this is the first time we've loaded this leaderboard
		// it will save the id even if there are no scores, yes. 
		// and we have to check again to see if we've posted the geeklist item
		// (which we DO only do if there are scores). it's not perfect, but
		// if we don't do it here the date is kind of a pain to pass in.
		if (!$row) {
			$query = "INSERT INTO spelunky_games(leaderboard_id, date) VALUES(" . $leaderboard . ", '" . date("Y-m-d",strtotime($date)) . "')";
			$db->query($query);
		}
	}

	return $leaderboard;
}

function get_saved_leaderboard($leaderboard_id) {
	global $db;

	$query = "SELECT spelunky_players.steamid, spelunky_players.name, score, level, character_used FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid WHERE leaderboard_id=" . $leaderboard_id . " ORDER BY score DESC";
	$result = $db->query($query);
	while ($row = $result->fetch_assoc()) {
		$leaderboard[$row['steamid']]['name'] = $row['name'];
		$leaderboard[$row['steamid']]['score'] = $row['score'];
		$leaderboard[$row['steamid']]['level'] = $row['level'];
		$leaderboard[$row['steamid']]['character'] = $row['character_used'];
	}

	return $leaderboard;
}

// grab the users in a group (easiest way to get everybody in the BGGWW community)
function get_group_members($group) {
	$xml = file_get_contents("http://steamcommunity.com/gid/" . $group . "/memberslistxml/?xml=1");
	$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($ob);
	$array = json_decode($json, TRUE);

	return $array['members']['steamID64'];
}

function get_player_info($steamid) {
	$xml = file_get_contents("http://steamcommunity.com/profiles/" . $steamid . "/?xml=1");
	$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($ob);
	$array = json_decode($json, TRUE);

	$info['steamid'] = $steamid;
	$info['name'] = $array['steamID'];
	$info['avatar'] = $array['avatarIcon'];

	return $info;
}

// insert or update player data
function update_player($player, $new = TRUE) {
	global $db;

	if ($new) $query = "INSERT INTO spelunky_players(steamid, name, avatar) VALUES(" . $player['steamid'] . ",'" . addslashes($player['name']) . "', '" . $player['avatar'] . "')";
	else $query = "UPDATE spelunky_players SET name='" . addslashes($player['name']) . "', avatar='" . $player['avatar'] . "' WHERE steamid=" . $player['steamid'];

	$db->query($query);
}

// I'll have to do work on this, so, uh, it doesn't exist
function get_youtube() {
}

// I'll have to do work on this, so, uh, it doesn't exist
function post_leaderboard() {
}

function save_leaderboard($leaderboard, $leaderboard_id) {
	global $db;
	$changed = FALSE;

	$query = "SELECT steamid FROM spelunky_game_entry WHERE leaderboard_id=" . $leaderboard_id;
	$result = $db->query($query);
	// if we have an entry, skip them
	while ($row = $result->fetch_assoc()) {
		if ($leaderboard[$row['steamid']]) {
			// it'd be super great if there was an array_remove type function besides array_slice, 
			// which doesn't work well/at all with associative arrays
			$leaderboard = array_remove($row['steamid'],$leaderboard);
		}
	}

	// is there anything that hasn't been inserted yet?
	if (count($leaderboard) > 0) {
		$changed = TRUE;

		foreach($leaderboard as $steamid=>$entry) {
			$query = "INSERT INTO spelunky_game_entry(steamid, leaderboard_id, score, level, character_used) VALUES(" . $steamid . ", " . $leaderboard_id . ", " . $entry['score'] . ", '" . $entry['level'] . "', " . $entry['character'] . ")";
			$db->query($query);
		}
	}

	// if we have new data, have we posted the geeklist yet?
	/*
	if ($changed) {
		$query = "SELECT geeklist FROM spelunky_games WHERE leaderboard_id=" . $leaderboard_id;
		$result = $db->query($query);
		$row = $result->fetch_assoc();
		if (!$row['geeklist']) {
		}
	}
	*/

	return $changed;
}

function print_leaderboard($leaderboard) {
	$i = 1;
	foreach ($leaderboard as $entry) {
		echo "<h2>" . $i . " <img src=\"images/char_" . character_icon($entry['character']) . ".png\" \>" . $entry['name'] . "</h2>\r";
		echo "<p><strong>Score</strong>: $" . $entry['score'] . "</p>";
		echo "<p><strong>Died on</strong>: " . $entry['level'] . "</p>";
		$i++;
	}
}

// by default, get today's leaderboard on BGG, but you can specify a date
function update_leaderboard($leaderboard, $date = FALSE) {
	if (!$date) $date = date('Y-m-d');
	echo "This doesn't do anything yet";
}

// if I want to do something with the character used
function character_icon($id) {
	$colors = array(
			'orange',
			'red',
			'green',
			'blue',
			'white',
			'pink',
			'yellow',
			'brown',
			'purple',
			'black',
			'cyan',
			'lime',
			'dlc1',
			'dlc2',
			'dlc3',
			'dlc4',
			'dlc5',
			'dlc6',
			'dlc7',
			'dlc8'
		       );

	return $colors[$id];
}

// I don't like this
function array_remove($needle, $haystack) {
	$tmp = array();

	foreach ($haystack as $key=>$value) if ($key !== $needle) $tmp[$key] = $value;

	return $tmp;
}
?>
