<?php 
$title = $_GET['player'];
require('header.inc.php');

$query = "SELECT date, spelunky_game_entry.leaderboard_id, score, level, character_used, spelunky_game_entry.steamid FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id WHERE lower(name)='" . addslashes(strtolower($_GET['player'])) . "' ORDER BY date ASC";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
	if ($row['score'] === "0") continue; // let's not be mean
	$games[] = $row;
	$characters[$row['character_used']]++;
	if (!$best || $best['score'] < $row['score']) $best = $row;
	if (!$farthest || $farthest['level'] < $row['level']) $farthest = $row;
	$scores[] = $row['score'];
	$levels[] = $row['level'];
}

// global bests to compare to
$global['score'] = get_best_score();
$global['level'] = get_best_level();

// get the most used character
arsort($characters);

// let's get some medians!
sort($scores);
$count = count($scores);
$middle = floor(($count-1)/2);
if ($count % 2) $median['score'] = $scores[$middle];
else $median['score'] = round(($scores[$middle]+$scores[$middle+1])/2);

sort($levels);
$count = count($levels);
$middle = floor(($count-1)/2);
if ($count % 2) $median['level'] = $levels[$middle];
else $median['level'] = round(($levels[$middle]+$levels[$middle+1])/2);

echo "<div class=\"textbox\">\r";
echo "<p><strong>Games played</strong>: " . count($games) . "<br />\r";
echo "<strong>First game</strong>: " . date("F j, Y",strtotime($games[0]['date'])) . " ($" . number_format($games[0]['score']) . ")<br />\r";
echo "<strong>Latest game</strong>: " . date("F j, Y",strtotime($games[count($games)-1]['date'])) . " ($" . number_format($games[count($games)-1]['score']) . ")</p>\r";

echo "<p><strong>Best score</strong>: $" . number_format($best['score']) . " (" . date("F j, Y",strtotime($best['date'])) . ")<br />\r";
echo "<strong>Average score</strong>: $" . number_format(round(array_sum($scores) / count($scores))) . "<br />";
echo "<strong>Median score</strong>: $" . number_format($median['score']) . "</p>";

echo "<p><strong>Farthest level reached</strong>: " . level($farthest['level']) . " (" . date("F j, Y",strtotime($farthest['date'])) . ")<br />\r";
echo "<strong>Average level reached</strong>: " . level(round(array_sum($levels) / count($levels))) . "<br />\r";
echo "<strong>Median level reached</strong>: " . level($median['level']) . "</p>";

echo "<p><strong>Favorite character</strong> (" . current($characters) . " times): <img src=\"/images/char_" . character_icon(key($characters)) . ".png\" /></p>\r";
echo "</div>\r";

echo "<h2>Game history</h2>"; // needs css
rsort($games);
?>
<table class="scoreboard">
<tr>
<th scope="col">Date</th>
<th scope="col">Score</th>
<th scope="col">Died on</th>
<th scope="col">Character</th>
<th scope="col">Awards</th>
</tr>
<?php
foreach ($games as $game) {
	echo "<tr>\r";
	echo "<td><a href=\"" . date("/Y/m/d/",strtotime($game['date'])) . "\">" . date("F j, Y",strtotime($game['date'])) . "</a></td>\r";
	echo "<td>$" . number_format($game['score']) . "</td>\r";
	echo "<td>" . level($game['level']) . "</td>\r";
	echo "<td><img src=\"/images/char_" . character_icon($game['character_used']) . ".png\" /></td>\r";
	echo "<td style=\"width:90px\">";
	if ($game['leaderboard_id'] == $global['score']['leaderboard_id'] && $game['steamid'] == $global['score']['steamid']) echo "<img src=\"/images/chalice.png\" style=\"width:37px;height:30px\" alt=\"Global highest score\" title=\"Global highest score\" />";
	if ($game['leaderboard_id'] == $global['level']['leaderboard_id'] && $game['steamid'] == $global['level']['steamid']) echo "<img src=\"/images/vladcape.png\" style=\"width:28px;height:30px\" alt=\"Global best level\" title=\"Global best level\" />";
	if ($game['leaderboard_id'] == $best['leaderboard_id']) echo "<img src=\"/images/idol.png\" style=\"width:24px;height:30px\" alt=\"Personal highest score\" title=\"Personal highest score\" />";
	if ($game['leaderboard_id'] == $farthest['leaderboard_id']) echo "<img src=\"/images/compass.png\" style=\"width:35px;height:30px\" alt=\"Personal best level\" title=\"Personal best level\" />";
	echo "</td>\r";
	echo "</tr>\r\r";
}
echo "</table>\r\r";
?>
