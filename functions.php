<?php
// pretty much everything useful here was stolen from the KlepekVsRemo repository:
// https://github.com/amarriner/KlepekVsRemo/

require('config.inc.php');
$db = new mysqli($dbinfo['server'],$dbinfo['username'],$dbinfo['password'],$dbinfo['database']);

class Leaderboard {
	private $date; // actually a timestamp
	private $leaderboard_id;
	private $leaderboard;
	private $members;

	private $stored = FALSE; // is it in the database already?

	// will grab the timestamp for the date provided (default today)
	// and check if it's been stored
	// if it has, grab the stored leaderboard
	function __construct($date = NULL) {
		global $db;

		// $this->date
		if (!$date) $this->date = time();
		else $this->date = strtotime($date);

		// $this->leaderboard_id
		// if it's not in the db, don't sweat it; we'll grab it during update()
		$query = "SELECT leaderboard_id FROM spelunky_games WHERE date='" . $this->get_date() . "'";
		$result = $db->query($query);
		if ($row = $result->fetch_assoc()) {
			$this->stored = TRUE;

			$this->leaderboard_id = $row['leaderboard_id'];

			// $this->leaderboard (don't bother checking if we don't have a lbid)
			$best['score'] = get_best_score();
			$best['level'] = get_best_level();

			$query = "SELECT spelunky_players.steamid, spelunky_players.name, score, level, character_used FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid WHERE leaderboard_id=" . $this->leaderboard_id . " ORDER BY score DESC, level DESC, name ASC";
			$result = $db->query($query);
			while ($row = $result->fetch_assoc()) {
				$this->leaderboard[$row['steamid']]['name'] = $row['name'];
				$this->leaderboard[$row['steamid']]['score'] = $row['score'];
				$this->leaderboard[$row['steamid']]['level'] = level($row['level']);
				$this->leaderboard[$row['steamid']]['character'] = $row['character_used'];

				unset($personal);
				$personal['score'] = get_best_score($row['steamid']);
				$personal['level'] = get_best_level($row['steamid']);

				if ($this->leaderboard_id == $best['score']['leaderboard_id'] && $row['steamid'] == $best['score']['steamid']) $this->leaderboard[$row['steamid']]['awards']['global_score'] = TRUE;
				if ($this->leaderboard_id == $personal['score']['leaderboard_id']) $this->leaderboard[$row['steamid']]['awards']['personal_score'] = TRUE;
				if ($this->leaderboard_id == $best['level']['leaderboard_id'] && $row['steamid'] == $best['level']['steamid']) $this->leaderboard[$row['steamid']]['awards']['global_level'] = TRUE;
				if ($this->leaderboard_id == $personal['level']['leaderboard_id']) $this->leaderboard[$row['steamid']]['awards']['personal_level'] = TRUE;
			}
		}
	}

	// PUBLIC FUNCTIONS
	// check if there are updates, and if so notify BGG
	public function update() {
		$new = $this->refresh();
		array_pop($this->leaderboard); // TAKE THIS OUT AFTER DEBUGGING!
		array_pop($this->leaderboard); // TAKE THIS OUT AFTER DEBUGGING!

		foreach ($new as $steamid=>$data) if (!@$this->leaderboard[$steamid]) $changes[$steamid] = $data;

		// new stuff!
		if ($changes) {
			// insert into db
			// check for geeklist
			// update BGG
			// comment

			// update the leaderboard
			// WILL NOT BE PROPERLY ORDERED
			$this->leaderboard = array_merge($changes,$this->leaderboard);

			// it's the first time we have results
			if (!$this->stored) {
				$query = "INSERT INTO spelunky_games(leaderboard_id, date) VALUES(" . $this->leaderboard_id . ", '" . $this->get_date() . "')";
				$db->query($query);
				$stored = TRUE;
			}

			return TRUE; // we made changes
		} else {
			return FALSE; // there weren't changes
		}
	}

	public function get_leaderboard() {
		return $this->leaderboard;
	}

	public function get_date($format = NULL) {
		if (!$format) return date("Y-m-d",$this->date);
		else return date($format,$this->date);
	}

	public function is_active() {
		// it's today AND before 7 EST, OR tomorrow AND after 7 today
		if ( (strtotime($this->get_date()) == strtotime(date("Y-m-d")) && date("G") < 19) || (strtotime($this->get_date()) == strtotime(date("Y-m-d",strtotime("tomorrow"))) && date("G") > 18)  ) return TRUE;
		else return FALSE;
	}

	public function display() {
		if (!count($this->leaderboard)) {
			echo "<p class=\"textbox\">There are no entries for this date</p>\r";
			return false;
		}  
		echo "<table id=\"scoreboard\">\r";
		echo "<tr>\r";
		echo "<th scope=\"col\">Rank</th>\r";
		echo "<th scope=\"col\">Player</th>\r";
		echo "<th scope=\"col\">Score</th>\r";
		echo "<th scope=\"col\">Died on</th>\r";
		echo "<th scope=\"col\">Character</th>\r";
		echo "<th scope=\"col\">Awards</th>\r";
		echo "</tr>\r\r";
		$i = 1;
		foreach ($this->leaderboard as $entry) {
			echo "<tr>\r";
			echo "<td>" . $i . "</td>\r";
			echo "<td><a href=\"/stats/" . $entry['name'] . "/\">" . $entry['name'] . "</a></td>\r";
			echo "<td>$" . number_format($entry['score']) . "</td>";
			echo "<td>" . $entry['level'] . "</td>";
			echo "<td><img src=\"/images/char_" . character_icon($entry['character']) . ".png\" \></td>\r";
			echo "<td style=\"width:90px;\">";
			if ($entry['awards']['global_score']) echo "<img src=\"/images/chalice.png\" style=\"width:37px;height:30px\" alt=\"Global highest score\" title=\"Global highest score\" />";
			if ($entry['awards']['global_level']) echo "<img src=\"/images/vladcape.png\" style=\"width:28px;height:30px\" alt=\"Global best level\" title=\"Global best level\" />";
			if ($entry['awards']['personal_score']) echo "<img src=\"/images/idol.png\" style=\"width:24px;height:30px\" alt=\"Personal highest score\" title=\"Personal highest score\" />";
			if ($entry['awards']['personal_level']) echo "<img src=\"/images/compass.png\" style=\"width:35px;height:30px\" alt=\"Personal best level\" title=\"Personal best level\" />";
			echo "</td>\r";
			echo "</tr>\r\r";
			$i++;
		}
		echo "</table>\r\r";
	}

	// etc

	// PRIVATE FUNCTIONS
	// refresh the data from Steam
	private function refresh() {
		global $db, $steam;

		// have we run update() before?
		if (!$this->members) $this->members = $this->get_group_members($steam['group']);

		// do we have a stored leaderboard_id?
		if (!$this->leaderboard_id) {
			$today = $this->get_date("m/d/Y") . ' DAILY';
			$url = 'http://steamcommunity.com/stats/239350/leaderboards/?xml=1';
			$xml = file_get_contents($url);
			$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
			$json = json_encode($ob);
			$array = json_decode($json, true);
			foreach($array['leaderboard'] as $key => $value) {
				if ($value['name'] == $today) {
					$this->leaderboard_id = $value['lbid'];
					break;
				}
			}

			if (!$this->leaderboard_id) die("Fatal error: no leaderboard for " . $this->get_date());
		}

		// using my id, then grabbing the scores for anyone in the group
		$player_spelunky_leaderboard = 'http://steamcommunity.com/stats/239350/leaderboards/' . $this->leaderboard_id . '/?xml=1&steamid=' . $steam['steamid64'];
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
			if (in_array($value['steamid'],$this->members)) {
				if ($value['score'] == "0") continue; // if you're in the middle of a run it will return 0
				$leaderboard[$value['steamid']]['score'] = $value['score'];

				// Characters are stored as hex values, converting to decimal
				$leaderboard[$value['steamid']]['character'] = hexdec(substr($value['details'], 0, 2));

				// Levels ares stored as hex values from 0-19, so convert them into what's shown on the leaderboards in-game
				$leaderboard[$value['steamid']]['level'] = hexdec(substr($value['details'], 8, 2));

				// do we know you?
				if (!$database[$value['steamid']]) { // also deal with if it was last updated X days ago
					$info = get_player_info($value['steamid']);
					update_player($info);

					$database[$value['steamid']]['name'] = $info['name'];
					$database[$value['steamid']]['avatar'] = $info['avatar'];
				}

				$leaderboard[$value['steamid']]['name'] = $database[$value['steamid']]['name'];
				$leaderboard[$value['steamid']]['avatar'] = $database[$value['steamid']]['avatar'];
			}
		}


		// IF THERE ARE PEOPLE IN THE GROUP NOT PRESENT, CHECK AGAIN?
		return $leaderboard;
	}

	// grab the users in a group (easiest way to get everybody in the BGGWW community)
	private function get_group_members($group) {
		$xml = file_get_contents("http://steamcommunity.com/gid/" . $group . "/memberslistxml/?xml=1");
		$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		$json = json_encode($ob);
		$array = json_decode($json, TRUE);

		return $array['members']['steamID64'];
	}
} // end class

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

function get_best_score($steamid = NULL) {
	global $db;
	// the ORDER BY is in case there's a tie; first come, first served
	$query = "SELECT leaderboard_id, steamid, score FROM spelunky_game_entry WHERE score=(SELECT max(score) FROM spelunky_game_entry";
	if ($steamid) $query .= " WHERE steamid=" . $steamid . ") AND steamid=" . $steamid;
	else $query .= ")";
	$query .= " ORDER BY leaderboard_id ASC";
	$result = $db->query($query);
	return $result->fetch_assoc();
}

function get_best_level($steamid = NULL) {
	global $db;
	// the ORDER BY is in case there's a tie; first come, first served
	$query = "SELECT leaderboard_id, steamid, level FROM spelunky_game_entry WHERE level=(SELECT max(level) FROM spelunky_game_entry";
	if ($steamid) $query .= " WHERE steamid=" . $steamid . ") AND steamid=" . $steamid;
	else $query .= ")";
	$query .= " ORDER BY leaderboard_id ASC";
	$result = $db->query($query);
	return $result->fetch_assoc();
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

function save_leaderboard($leaderboard, $leaderboard_id) {
	global $db;
	$changed = FALSE;
	$original_leaderboard = $leaderboard;

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
		$comment = "";

		$best['score'] = get_best_score();
		$best['level'] = get_best_level();

		foreach($leaderboard as $steamid=>$entry) {
			$query = "INSERT INTO spelunky_game_entry(steamid, leaderboard_id, score, level, character_used) VALUES(" . $steamid . ", " . $leaderboard_id . ", " . $entry['score'] . ", '" . $entry['level'] . "', " . $entry['character'] . ")";
			$db->query($query);

			// you did it
			//$comment .= $entry['name'] . " completed the daily challenge, scoring $" . number_format($entry['score']) . " and dying on " . level($entry['level']) . "\n";

			// did you beat your own best?
			unset($personal);
			$personal['score'] = get_best_score($row['steamid']);
			$personal['level'] = get_best_level($row['steamid']);
			if ($entry['score'] > $personal['score']) $comment .= "[i]" . $entry['name'] . " beat their personal high score![/i]\n\n";
			if ($entry['level'] > $personal['level']) $comment .= "[i]" . $entry['name'] . " beat their farthest level![/i]\n\n";

			// did you beat everyone else ever omg?
			if ($entry['score'] > $best['score']) $comment .= "[b]" . $entry['name'] . " beat the all-time high score![/b]\n\n";
			if ($entry['level'] > $best['level']) $comment .= "[b]" . $entry['name'] . " beat the all-time farthest level![/b]\n\n";
		}

		if (!is_logged_in()) login();
		$glid = update_leaderboard($original_leaderboard,$leaderboard_id);
		if ($comment) geeklist_comment($comment, $glid); // alert people of new scores
		echo count($leaderboard) . " new entries imported";
	}

	return $changed;
}


function level($level) {
	return ceil($level / 4) . "-" . ($level % 4 == 0? 4 : ($level % 4));
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


/***** BGG FUNCTIONS *****/
// create a new geeklist, submit it, enter the geeklist_id in the database, return the geeklsit_id
function new_geeklist() {
	global $db, $bgg;

	$ch = curl_init("http://videogamegeek.com/geeklist/save");
	curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
	curl_setopt($ch, CURLOPT_POST, TRUE);

	// the meat of it
	$description = "The BGG Werewolf community takes on the Spelunky daily challenges. And die. A lot.\n\nIf you're a BGGWWer, add your score (and, optionally, video: see [url=http://boardgamegeek.com/article/14563725#14563725]how to record with ffsplit for PC[/url]) as a comment to the day's entry. Since the game resets in the evening, take the \"day\" of the challenge to be the day it was live at noon.";
	$data = array(
			"listid"		=>	NULL,
			"action"		=>	"savelist",
			"geek_link_select_1"	=>	NULL,
			"sizesel"		=>	"10",
			"title"			=>	"Spelunking Werewolves: " . date("F Y") . " edition!",
			"description"		=>	$description,
			"subscribe"		=>	"1",
			"domains[videogame]"	=>	"1",
			"allowcomments"		=>	"1",
			"specialsort"		=>	"none",
			"B1"			=>	"Save %26 Continue To Step 2"
		     );

	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	preg_match("/([0-9]+)/",$info['redirect_url'],$matches);
	$geeklist_id = $matches[1];

	if (!$geeklist_id) {
		echo "FATAL ERROR: No geeklist id";
		return FALSE;
	}

	// we entered the geeklist, now we need to submit it
	$ch = curl_init("http://videogamegeek.com/geeklist/submit/" . $geeklist_id);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	// if you want to geekmail everyone, do it here
	$query = "INSERT INTO spelunky_geeklists(date, geeklist_id) VALUES('" . date("Y") . "-" . date("m") . "-01', " . $geeklist_id . ")";
	$db->query($query);

	return $geeklist_id;
}

function geeklist_entry($leaderboard,$geeklist_id, $item_id = 0) {
	global $db, $bgg;

	$ch = curl_init("http://videogamegeek.com/geeklist/item/save");
	curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
	curl_setopt($ch, CURLOPT_POST, TRUE);

	$data = array(
			"action"		=>	"save",
			"listid"		=>	$geeklist_id,
			"itemid"		=>	$item_id,
			"objectid"		=>	"73701", // Spelunky
			"geekitemname"		=>	"Spelunky",
			"objecttype"		=>	"thing",
			"imageid"		=>	"1850139", // the HD image
			"geek_link_select_1"	=>	NULL,
			"sizesel"		=>	"10",
			"comments"		=>	format_leaderboard($leaderboard),
			"B1"			=>	"Save"
		     );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	preg_match("/([0-9]+$)/",$info['redirect_url'],$matches);
	$glid = $matches[1];
	if (!$glid) echo "*** Error with url " . $info['redirect_url'] . " in geeklist_entry(\$leaderboard," . $geeklist_id . "," . $item_id;
	return $glid;
}

function geeklist_comment($comment, $item_id) {
	global $bgg;

	$ch = curl_init("http://videogamegeek.com/geekcomment.php");
	curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
	curl_setopt($ch, CURLOPT_POST, TRUE);

	$data = array(
			"action"		=>	"save",
			"objectid"		=>	$item_id,
			"objecttype"		=>	"listitem",
			"geek_link_select_1"	=>	NULL,
			"sizesel"		=>	"10",
			"body"			=>	$comment,
			"ajax"			=>	"1", // but is it?
			"B1"			=>	"Save"
		     );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
}

// expects date("Y-m")
function get_geeklist($month) {
	global $db;

	$query = "SELECT geeklist_id FROM spelunky_geeklists WHERE date='" . $month . "-01'";
	$result = $db->query($query);
	$row = $result->fetch_assoc();

	return $row['geeklist_id'];
}

function login() {
	global $bgg;
	if (!$bgg['username'] || !$bgg['password'] || !$bgg['cookiejar']) return FALSE; // you haven't set options, so no BGG stuff

	$ch = curl_init("http://videogamegeek.com/login");
	curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	// we're only calling this if we need new cookies
	//curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
	curl_setopt($ch, CURLOPT_POSTFIELDS, array("username" => $bgg['username'], "password"=> $bgg['password']));

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
}

function is_logged_in() {
	global $bgg;
	if (!$bgg['cookiejar']) return FALSE;
	$logged_in = FALSE;
	$cookies = extractCookies(@file_get_contents($bgg['cookiejar']));
	if (!$cookies) return FALSE;

	foreach ($cookies as $cookie) {
		if ($cookie['domain'] == ".videogamegeek.com") {
			$logged_in = TRUE;
			break;
		}
	}
	return $logged_in;
}

/**
 * Extract any cookies found from the cookie file. This function expects to get
 * a string containing the contents of the cookie file which it will then
 * attempt to extract and return any cookies found within.
 *
 * @param string $string The contents of the cookie file.
 * 
 * @return array The array of cookies as extracted from the string.
 *
 * From http://www.hashbangcode.com/blog/netscape-http-cooke-file-parser-php-584.html
 */
function extractCookies($string) {
	$cookies = array();

	$lines = explode("\n", $string);

	// iterate over lines
	foreach ($lines as $line) {

		// we only care for valid cookie def lines
		if (isset($line[0]) && substr_count($line, "\t") == 6) {

			// get tokens in an array
			$tokens = explode("\t", $line);

			// trim the tokens
			$tokens = array_map('trim', $tokens);

			$cookie = array();

			// Extract the data
			$cookie['domain'] = $tokens[0];
			$cookie['flag'] = $tokens[1];
			$cookie['path'] = $tokens[2];
			$cookie['secure'] = $tokens[3];

			// Convert date to a readable format
			$cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);

			$cookie['name'] = $tokens[5];
			$cookie['value'] = $tokens[6];

			// Record the cookie.
			$cookies[] = $cookie;
		}
	}

	return $cookies;
}

function update_leaderboard($leaderboard, $leaderboard_id) {
	global $db;

	$query = "SELECT geeklist FROM spelunky_games WHERE leaderboard_id=" . $leaderboard_id;
	$result = $db->query($query);
	if ($result) {
		$row = $result->fetch_assoc();
		$geeklist_item = $row['geeklist']; // if it's null, we'll auto-create one later
	} else {
		$geeklist_item = NULL;
	}

	$geeklist_id = get_geeklist(date("Y-m"));
	if (!$geeklist_id) $geeklist_id = new_geeklist();

	$glid = geeklist_entry($leaderboard,$geeklist_id,$geeklist_item);

	if ($geeklist_item != $glid) {
		$query = "UPDATE spelunky_games SET geeklist=" . $glid . " WHERE leaderboard_id=" . $leaderboard_id;
		$db->query($query);
	}

	return $glid;
}

function format_leaderboard($leaderboard, $date = NULL) {
	global $siteurl;

	if (!$date) {
		if (date("G") < 19) $date = date("F j");
		else $date = date("F j",strtotime("tomorrow"));
	}
	$return = "[size=16][b]" . $date . "[/b][/size]\n\n";

	// let's make this look all pretty-like
	$i = 1;
	$line = "---------------------------------------------------\n";
	$return .= "[o][c]" . $line;
	$return .= "| Rank |       Player       |     Score    | Died |\n" . $line;
	foreach ($leaderboard as $entry) {
		$return .= "| " . $i;
		if ($i < 10) $return .= " ";
		$return .= "   | ";
		$return .= $entry['name'];
		for ($j = 0; $j < (19 - strlen($entry['name'])); $j++) $return .= " ";
		$score = number_format($entry['score']);
		$return .= "| ";
		for ($j = 0; $j < (11 - strlen($score)); $j++) $return .= " ";
		$return .= "$" . $score . " ";
		$return .= "| " . level($entry['level']) . "  |\n" . $line;
		$i++;
	}
	$return .= "[/o]\n";
	$return .= "[url=" . $siteurl . "/" . date("Y/m/d",strtotime($date)) . "/]Full leaderboard[/url][/c]";
	return $return;
}

?>
