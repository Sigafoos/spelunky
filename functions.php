<?php

// pretty much everything useful here was stolen from the KlepekVsRemo repository:
// https://github.com/amarriner/KlepekVsRemo/

// Checks to see if the player's data has already been tweeted
function check_today($player) {
	$found = 1;

	$last = trim(file_get_contents(_PWD . '/' . $player->steamid));
	if ($last != date('m/d/Y')) {
		$found = 0;
	}

	return $found;
}

// Retrieves the player's leaderboard data for today
function get_leaderboard_data($members, $leaderboard) {
	//$steamid = $player->steamid; // I have their id
	$score = -1;

	// using my id, then grabbing the scores for anyone in the group
	$player_spelunky_leaderboard = 'http://steamcommunity.com/stats/239350/leaderboards/' . $leaderboard . '/?xml=1&steamid=76561198000338942';

	$xml = file_get_contents($player_spelunky_leaderboard);
	$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($ob);
	$array = json_decode($json, true);
	/*
	   echo "<pre>";
	   print_r($array);
	   echo "</pre>";
	   */

	foreach($array['entries']['entry'] as $key => $value) {
		if (in_array($value['steamid'],$members)) {
			$scores[$value['steamid']]['score'] = $value['score'];

			// Characters are stored as hex values, converting to decimal
			$scores[$value['steamid']]['character'] = hexdec(substr($value['details'], 0, 2));

			// Levels ares stored as hex values from 0-19, so convert them into what's shown on the leaderboards in-game
			$level = hexdec(substr($value['details'], 8, 2));
			$scores[$value['steamid']]['level'] = ceil($level / 4) . "-" . ($level % 4 == 0? 4 : ($level % 4));
		}
	}

	return $scores;
}

// Find the id for today's daily challenge leaderboard
function get_todays_leaderboard() {
	$leaderboard = '';

	$today = date('m/d/Y') . ' DAILY';
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

// I'll have to do work on this, so, uh, it doesn't exist
function get_youtube() {
}

// I'll have to do work on this, so, uh, it doesn't exist
function post_leaderboard() {
}

// I'll have to do work on this, so, uh, it doesn't exist
function store_leaderboard() {
}

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
			'dlc7',
			'dlc8'
		       );

	// stuff
}
?>
