<?php 
if (!$_GET['player']) header("Location:index.php");
$title = $_GET['player'];
require('header.inc.php');

$query = "SELECT date, spelunky_game_entry.leaderboard_id, score, level, character_used FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id WHERE lower(name)='" . addslashes(strtolower($_GET['player'])) . "' ORDER BY date ASC";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
	if ($row['score'] === "0") continue; // let's not be mean
	$games[] = $row;
	$characters[$row['character_used']]++;
	if (!$best || $best['score'] < $row['score']) $best = $row;
	//if (!$farthest || $best['score'] < $row['score']) $best = $row;
	$scores[] = $row['score'];
	$levels[] = $row['level'];
}
arsort($characters);

echo "<div class=\"textbox\">\r";
echo "<p><strong>Games played</strong>: " . count($games) . "<br />\r";
echo "<strong>First game</strong>: " . date("F j, Y",strtotime($games[0]['date'])) . " ($" . number_format($games[0]['score']) . ")<br />\r";
echo "<strong>Latest game</strong>: " . date("F j, Y",strtotime($games[count($games)-1]['date'])) . " ($" . number_format($games[count($games)-1]['score']) . ")</p>\r";

echo "<p><strong>Best score</strong>: $" . number_format($best['score']) . " (" . date("F j, Y",strtotime($best['date'])) . ")<br />\r";
echo "<strong>Average score</strong>: $" . number_format(round(array_sum($scores) / count($scores))) . "</p>";

echo "<p><strong>Average level reached</strong>: " . level(round(array_sum($levels) / count($levels))) . "</p>\r";

echo "<p><strong>Favorite character</strong> (" . current($characters) . " times): <img src=\"images/char_" . character_icon(key($characters)) . ".png\" /></p>\r";
echo "</div>\r";

// echo "<h2>Game history</h2>"; // needs css
rsort($games);
?>
<table id="scoreboard">
<tr>
<th scope="col">Date</th>
<th scope="col">Score</th>
<th scope="col">Died on</th>
<th scope="col">Character</th>
</tr>
<?php
foreach ($games as $game) {
	echo "<tr>\r";
	echo "<td><a href=\"/?date=" . date("Y-m-d",strtotime($game['date'])) . "\">" . date("F j, Y",strtotime($game['date'])) . "</a></td>\r";
	echo "<td>$" . number_format($game['score']) . "</td>\r";
	echo "<td>" . level($game['level']) . "</td>\r";
	echo "<td><img src=\"images/char_" . character_icon($game['character_used']) . ".png\" /></td>\r";
	echo "</tr>\r\r";
}
echo "</table>\r\r";
require('footer.inc.php');
?>
