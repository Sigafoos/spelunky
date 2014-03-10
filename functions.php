<?php
// pretty much everything useful here was stolen from the KlepekVsRemo repository:
// https://github.com/amarriner/KlepekVsRemo/

require('config.inc.php');
$db = new mysqli($dbinfo['server'],$dbinfo['username'],$dbinfo['password'],$dbinfo['database']);

class Leaderboard {
	private $date; // actually a timestamp
	private $leaderboard_id;
	private $leaderboard;
	private $geeklist_item;
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

		// $this->leaderboard
		// needs to be initialized here or else things can get weird later
		$this->leaderboard = array();

		// I'm abstracting the Spelunky data so the geeklist code can be reused
		$geeklist_data = array(
				"objectid"	=>	"73701",
				"geekitemname"	=>	"Spelunky",
				"imageid"	=>	"1850139"
				);

		// $this->leaderboard_id
		// if it's not in the db, don't sweat it; we'll grab it during update()
		$query = "SELECT leaderboard_id, geeklist FROM spelunky_games WHERE date='" . $this->get_date() . "'";
		$result = $db->query($query);
		if ($row = $result->fetch_assoc()) {
			$this->stored = TRUE;

			$this->leaderboard_id = $row['leaderboard_id'];

			// build the geeklist
			$this->geeklist = new Geeklist($row['geeklist'],$geeklist_data); // if it's null, we'll auto-create one later (but it shouldn't be)

			// $this->leaderboard (don't bother checking if we don't have a lbid)
			$best['score'] = get_best_score();
			$best['level'] = get_best_level();

			$query = "SELECT spelunky_players.steamid, spelunky_players.name, score, level, character_used FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid WHERE leaderboard_id=" . $this->leaderboard_id . " ORDER BY score DESC, level DESC, name ASC";
			$result = $db->query($query);
			while ($row = $result->fetch_assoc()) {
				$this->leaderboard[$row['steamid']]['name'] = $row['name'];
				$this->leaderboard[$row['steamid']]['score'] = $row['score'];
				$this->leaderboard[$row['steamid']]['level'] = $row['level'];
				$this->leaderboard[$row['steamid']]['character'] = $row['character_used'];

				unset($personal);
				$personal['score'] = get_best_score($row['steamid']);
				$personal['level'] = get_best_level($row['steamid']);

				if ($this->leaderboard_id == $best['score']['leaderboard_id'] && $row['steamid'] == $best['score']['steamid']) $this->leaderboard[$row['steamid']]['awards']['global_score'] = TRUE;
				if ($this->leaderboard_id == $personal['score']['leaderboard_id']) $this->leaderboard[$row['steamid']]['awards']['personal_score'] = TRUE;
				if ($this->leaderboard_id == $best['level']['leaderboard_id'] && $row['steamid'] == $best['level']['steamid']) $this->leaderboard[$row['steamid']]['awards']['global_level'] = TRUE;
				if ($this->leaderboard_id == $personal['level']['leaderboard_id']) $this->leaderboard[$row['steamid']]['awards']['personal_level'] = TRUE;
			}
		} else {
			$this->geeklist = new Geeklist(NULL,$geeklist_data);
		}
	}

	// PUBLIC FUNCTIONS
	// check if there are updates, and if so notify BGG
	public function update() {
		$new = $this->refresh();
		$changes = array();
		foreach ($new as $steamid=>$data) if (!@$this->leaderboard[$steamid]) $changes[$steamid] = $data;

		// new stuff!
		if ($changes) {
			echo count($changes) . " new entries detected...\n";
			global $db;
			$comments = "";

			$best['score'] = get_best_score();
			$best['level'] = get_best_level();

			foreach($changes as $steamid=>$entry) {
				// you did it
				//$comments .= $entry['name'] . " completed the daily challenge, scoring $" . number_format($entry['score']) . " and dying on " . level($entry['level']) . "\n";

				// did you beat your own best?
				unset($personal);
				$personal['score'] = get_best_score($steamid);
				$personal['level'] = get_best_level($steamid);

				if ($entry['score'] > $personal['score']['score']) $comments .= "[i]" . $entry['name'] . " beat their personal high score![/i]\n\n";
				if ($entry['level'] > $personal['level']['level']) $comments .= "[i]" . $entry['name'] . " beat their farthest level![/i]\n\n";

				// did you beat everyone else ever omg?
				if ($entry['score'] > $best['score']['score']) $comments .= "[b]" . $entry['name'] . " beat the all-time high score![/b]\n\n";
				if ($entry['level'] > $best['level']['level']) $comments .= "[b]" . $entry['name'] . " beat the all-time farthest level![/b]\n\n";

				$query = "INSERT INTO spelunky_game_entry(steamid, leaderboard_id, score, level, character_used) VALUES(" . $steamid . ", " . $this->leaderboard_id . ", " . $entry['score'] . ", '" . $entry['level'] . "', " . $entry['character'] . ")";
				$db->query($query);
			}
			echo "Entries imported.\n";

			// it's the first time we have results
			if (!$this->stored) {
				$query = "INSERT INTO spelunky_games(leaderboard_id, date) VALUES(" . $this->leaderboard_id . ", '" . $this->get_date() . "')";
				$db->query($query);
				$stored = TRUE;
			}

			// update the leaderboard with new values
			$this->leaderboard = array_merge($changes,$this->leaderboard);
			$this->sort();
			echo "Leaderboard sorted.\n";

			echo "Updating geeklist...\n";
			$this->update_geeklist();

			// alert people of new high scores
			if ($comments) {
				echo "Sending comments...\n";
				$this->geeklist->comment($comments); 
			}

			return TRUE; // we made changes
		} else {
			return FALSE; // there weren't changes
		}
	}

	// edit the geeklist item
	public function update_geeklist() {
		if (!$this->leaderboard) die("\033[1mError:\033[0m No players have completed the challenge for " . $this->get_date() . "\n");

		global $db;

		// so we can post
		if (!is_logged_in()) login();

		// the main geeklist
		if (!$this->geeklist->get_geeklist_id()) {
			$query = "SELECT geeklist_id FROM spelunky_geeklists WHERE date='" . $this->get_date("Y-m") . "-01'";
			$result = $db->query($query);
			if ($row = $result->fetch_assoc()) {
				$this->geeklist->set_geeklist_id($row['geeklist_id']);
				echo "Fetched geeklist id: " . $this->geeklist->get_geeklist_id() . "\n";
			} else {
				$this->geeklist->new_geeklist("Spelunking Werewolves: " . $this->get_date("F Y") . " edition!","The BGG Werewolf community takes on the Spelunky daily challenges. And die. A lot.\n\nIf you're a BGGWWer, add your score (and, optionally, video: see [url=http://boardgamegeek.com/article/14563725#14563725]how to record with ffsplit for PC[/url]) as a comment to the day's entry. Since the game resets in the evening, take the \"day\" of the challenge to be the day it was live at noon.");

				// if you want to geekmail everyone, do it here
				$query = "INSERT INTO spelunky_geeklists(date, geeklist_id) VALUES('" . $this->get_date("Y-m") . "-01', " . $this->geeklist->get_geeklist_id() . ")";
				$db->query($query);
				echo "Inserted new geeklist id: " . $this->geeklist->get_geeklist_id() . "\n";
				$this->geekmail_players();
			}
		}

		if ($this->geeklist->item($this->format("geeklist"))) {
			echo "New geeklist item created: " . $this->geeklist->get_geeklist_item() . "\n";
			// do we not have a glid (probably only if it's the first run through)
			$query = "UPDATE spelunky_games SET geeklist=" . $this->geeklist->get_geeklist_item() . " WHERE leaderboard_id=" . $this->leaderboard_id;
			$db->query($query);
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

	public function format($format = "table") {
		if ($format == "table") {
			if (!count($this->leaderboard)) {
				return "<p class=\"textbox\">There are no entries for this date</p>\r";
			}  
			$return = "<table class=\"scoreboard\">\r";
			$return .= "<tr>\r";
			$return .= "<th scope=\"col\">Rank</th>\r";
			$return .= "<th scope=\"col\">Player</th>\r";
			$return .= "<th scope=\"col\">Score</th>\r";
			$return .= "<th scope=\"col\">Died on</th>\r";
			$return .= "<th scope=\"col\">Character</th>\r";
			$return .= "<th scope=\"col\">Awards</th>\r";
			$return .= "</tr>\r\r";
			$i = 1;
			foreach ($this->leaderboard as $entry) {
				$return .= "<tr>\r";
				$return .= "<td>" . $i . "</td>\r";
				$return .= "<td><a href=\"/stats/" . $entry['name'] . "/\">" . $entry['name'] . "</a></td>\r";
				$return .= "<td>$" . number_format($entry['score']) . "</td>";
				$return .= "<td>" . level($entry['level']) . "</td>";
				$return .= "<td><img src=\"/images/char_" . character_icon($entry['character']) . ".png\" \></td>\r";
				$return .= "<td style=\"width:90px;\">";
				if ($entry['awards']['global_score']) $return .= "<img src=\"/images/chalice.png\" style=\"width:37px;height:30px\" alt=\"Global highest score\" title=\"Global highest score\" />";
				if ($entry['awards']['global_level']) $return .= "<img src=\"/images/vladcape.png\" style=\"width:28px;height:30px\" alt=\"Global best level\" title=\"Global best level\" />";
				if ($entry['awards']['personal_score']) $return .= "<img src=\"/images/idol.png\" style=\"width:24px;height:30px\" alt=\"Personal highest score\" title=\"Personal highest score\" />";
				if ($entry['awards']['personal_level']) $return .= "<img src=\"/images/compass.png\" style=\"width:35px;height:30px\" alt=\"Personal best level\" title=\"Personal best level\" />";
				$return .= "</td>\r";
				$return .= "</tr>\r\r";
				$i++;
			}
			$return .= "</table>\r\r";

		} else if ($format == "geeklist") {
			global $siteurl;

			$return = "[size=16][b]" . $this->get_date("F j, Y") . "[/b][/size]\n\n";

			$return .= "[c]";
			// let's make this look all pretty-like
			if ($this->is_active()) {
				$names = array();
				foreach ($this->leaderboard as $entry) $names[] = $entry['name'];
				$return .= "The challenge has been completed by:\n\n";
				if ($names) {
					natcasesort($names);
					$return .= implode("\n",$names);
				}
				$return .= "\n\nThe full leaderboard will be posted at 6 pm BGG.\n";
			} else { // it's done, print the leaderboard
				$i = 1;
				$line = "---------------------------------------------------\n";
				$return .= $line;
				$return .= "| Rank |       Player       |     Score    | Died |\n" . $line;
				foreach ($this->leaderboard as $entry) {
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
				$return .= "\n";
			}
			$return .= "[url=" . $siteurl . "/" . $this->get_date("Y/m/d") . "/]Full leaderboard[/url][/c]";
		} else if ($format == "text") {
			$i = 1;
			$line = "---------------------------------------------------\n";
			$return = $line;
			$return .= "| Rank |       Player       |     Score    | Died |\n" . $line;
			foreach ($this->leaderboard as $entry) {
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
			$return .= "\n";
		}

		return $return;

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
			if (!$array) die("\033[1mFatal error\033[0m: Unable to retrieve leaderboard id\n");
			foreach($array['leaderboard'] as $key => $value) {
				if ($value['name'] == $today) {
					$this->leaderboard_id = $value['lbid'];
					break;
				}
			}

			if (!$this->leaderboard_id) die("\033[1mFatal error\033[0m: no leaderboard for " . $this->get_date() . "\n");
		}

		// using my id, then grabbing the scores for anyone in the group
		$player_spelunky_leaderboard = 'http://steamcommunity.com/stats/239350/leaderboards/' . $this->leaderboard_id . '/?xml=1&steamid=' . $steam['steamid64'];
		$xml = file_get_contents($player_spelunky_leaderboard);
		$ob = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		$json = json_encode($ob);
		$array = json_decode($json, true);
		if (!$array) die("\033[1mFatal error\033[0m: Unable to retrieve leaderboard data\n");

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
		if (!$array) die("\033[1mFatal error\033[0m: Unable to retrieve group members\n");

		return $array['members']['steamID64'];
	}

	private function sort() {
		return uasort($this->leaderboard,"sort_leaderboard");
	}

	public function geekmail_players() {
		global $db;

		$query = "SELECT name FROM spelunky_players WHERE steamid IN (SELECT DISTINCT steamid FROM spelunky_game_entry INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id WHERE date > DATE_SUB(NOW(), INTERVAL 1 MONTH))";
		$result = $db->query($query);
		while ($row = $result->fetch_assoc()) $players[] = $row['name'];

		$message = "Hello, Spelunker!\n\nThere's a new monthly geeklist up: http://videogamegeek.com/geeklist/" . $this->geeklist->get_geeklist_id() . "/\n\n[size=6]This is an automated message. If you have something to say about it, [url=http://videogamegeek.com/geekmail/compose?touser=Sigafoos]bug Sigafoos[/url][/size]";
		geekmail(implode(",",$players),date("F") . " Spelunky geeklist",$message);
	}
} // end class

// sorts the leaderboard based on score, then level, then name
function sort_leaderboard($a, $b) {
	if ($a['score'] < $b['score']) return 1;
	else if ($a['score'] > $b['score']) return -1;
	// scores are equal, compare levels
	else if ($a['level'] < $b['level']) return 1;
	else if ($a['level'] > $b['level']) return -1;
	// okaaay, how about names?
	else return -(strcmp($a['name'],$b['name']));
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

// not in the class because they don't relate to a specific leaderboard
function get_best_score($steamid = NULL, $formatted = FALSE) {
	global $db;
	if ($formatted) $query = "SELECT date, name, score FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id";
	else $query = "SELECT leaderboard_id, steamid, score FROM spelunky_game_entry";
	$query .= " WHERE score=(SELECT max(score) FROM spelunky_game_entry";
	if ($steamid) $query .= " WHERE spelunky_game_entry.steamid=" . $steamid . ") AND spelunky_game_entry.steamid=" . $steamid;
	else $query .= ")";
	// the ORDER BY is in case there's a tie; first come, first served
	$query .= " ORDER BY spelunky_game_entry.leaderboard_id ASC";
	$result = $db->query($query);
	return $result->fetch_assoc();
}

function get_best_level($steamid = NULL, $formatted = FALSE) {
	global $db;
	if ($formatted) $query = "SELECT date, name, level FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id";
	else $query = "SELECT leaderboard_id, steamid, level FROM spelunky_game_entry";
	$query .= " WHERE level=(SELECT max(level) FROM spelunky_game_entry";
	if ($steamid) $query .= " WHERE spelunky_game_entry.steamid=" . $steamid . ") AND spelunky_game_entry.steamid=" . $steamid;
	else $query .= ")";
	// the ORDER BY is in case there's a tie; first come, first served
	$query .= " ORDER BY spelunky_game_entry.leaderboard_id ASC";
	$result = $db->query($query);
	return $result->fetch_assoc();
}

function get_average_score($steamid = NULL) {
	global $db;
	$query = "SELECT avg(score) FROM spelunky_game_entry";
	if ($steamid) $query .= " WHERE steamid=" . $steamid;
	$result = $db->query($query);
	return $result->fetch_assoc();
}

function get_average_level($steamid = NULL) {
	global $db;
	$query = "SELECT avg(level) FROM spelunky_game_entry";
	if ($steamid) $query .= " WHERE steamid=" . $steamid;
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


class Geeklist {
	private $geeklist_id; // the main list
	private $geeklist_item;
	private $game; // what you're updating (if you want to change multiple things, leave this null on construction and write a function for it)

	function __construct($geeklist_item = 0, $game = NULL) {
		if ($geeklist_item === NULL) $geeklist_item = 0;
		$this->geeklist_item = $geeklist_item;

		if ($game) $this->game = $game;
	}

	// create a new geeklist, submit it, enter the geeklist_id in the database, return the geeklist_id
	// NOT UPDATED
	public function new_geeklist($title, $description) {
		global $db, $bgg;

		$ch = curl_init("http://videogamegeek.com/geeklist/save");
		curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
		curl_setopt($ch, CURLOPT_POST, TRUE);

		// the meat of it
		$data = array(
				"listid"		=>	NULL,
				"action"		=>	"savelist",
				"geek_link_select_1"	=>	NULL,
				"sizesel"		=>	"10",
				"title"			=>	$title,
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
		$this->geeklist_id = $matches[1];

		if (!$this->geeklist_id) {
			echo "FATAL ERROR: No geeklist id";
			return FALSE;
		}

		// we entered the geeklist, now we need to submit it
		$ch = curl_init("http://videogamegeek.com/geeklist/submit/" . $this->geeklist_id);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu

		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
	}

	// update/post a geeklist item
	// returns TRUE if item was created
	public function item($comment) {
		if (!$this->game) {
			die("\033[1mError:\033[0m I don't know what game to use for the item. Specify upon construction.\n");
		} else if (!$this->geeklist_id) {
			die("\033[1mError:\033[0m No main geeklist created. Use \$this->get_geeklist_id() to retrieve or \$this->new_geeklist() to create.\n");
		}

		if (!$this->geeklist_id) echo "Inserting new geeklist item...\n";
		else echo "Updating geeklist item...\n";

		global $db, $bgg;

		$ch = curl_init("http://videogamegeek.com/geeklist/item/save");
		curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
		curl_setopt($ch, CURLOPT_POST, TRUE);

		$data = array(
				"action"		=>	"save",
				"listid"		=>	$this->geeklist_id,
				"itemid"		=>	$this->geeklist_item,
				"objectid"		=>	$this->game['objectid'],
				"geekitemname"		=>	$this->game['geekitemname'],
				"objecttype"		=>	"thing",
				"imageid"		=>	$this->game['imageid'],
				"geek_link_select_1"	=>	NULL,
				"sizesel"		=>	"10",
				"comments"		=>	$comment,
				"B1"			=>	"Save"
			     );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		preg_match("/([0-9]+$)/",$info['redirect_url'],$matches);
		$glid = $matches[1];
		if (!$glid) echo "*** Error with url " . $info['redirect_url'] . "\n";

		// update the geeklist item
		if ($glid != $this->geeklist_item) {
			$this->geeklist_item = $glid;
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// NOT TESTED
	public function comment($comment) {
		global $bgg;

		$ch = curl_init("http://videogamegeek.com/geekcomment.php");
		curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
		curl_setopt($ch, CURLOPT_POST, TRUE);

		$data = array(
				"action"		=>	"save",
				"objectid"		=>	$this->geeklist_item,
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

	public function get_geeklist_id() {
		return $this->geeklist_id;
	}

	public function get_geeklist_item() {
		return $this->geeklist_item;
	}

	public function set_geeklist_id($id) {
		$this->geeklist_id = $id;
	}

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

function geekmail($to, $subject, $message) {
	global $bgg;
	if (!is_logged_in()) login();

	$ch = curl_init("http://videogamegeek.com/geekmail_controller.php");
	curl_setopt($ch, CURLOPT_COOKIEJAR, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $bgg['cookiejar']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // stfu
	curl_setopt($ch, CURLOPT_POST, TRUE);
	$data = array(
			"action"		=>	"save",
			"messageid"		=>	NULL,
			"touser"		=>	$to,
			"subject"		=>	$subject,
			"savecopy"		=>	1,
			"geek_link_select_1"	=>	NULL,
			"sizesel"		=>	10,
			"body"			=>	$message,
			"B1"			=>	"Send",
			"folder"		=>	"inbox",
			"label"			=>	NULL,
			"ajax"			=>	1,
			"searchid"		=>	0,
			"pageID"		=>	0
		     );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
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
?>
