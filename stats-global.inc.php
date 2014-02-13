<?php
$title = "Statistics";
require('header.inc.php');

$best['score'] = get_best_score(NULL,TRUE);
$best['level'] = get_best_level(NULL,TRUE);

// total players
$query = "SELECT count(*) FROM spelunky_players";
$result = $db->query($query);
$row = $result->fetch_assoc();
$total['players'] = $row['count(*)'];

// total days played
$query = "SELECT count(*) FROM spelunky_games";
$result = $db->query($query);
$row = $result->fetch_assoc();
$total['days'] = $row['count(*)'];

// total games played
$query = "SELECT count(*) FROM spelunky_game_entry";
$result = $db->query($query);
$row = $result->fetch_assoc();
$total['games'] = $row['count(*)'];

// total score
$query = "SELECT sum(score) FROM spelunky_game_entry";
$result = $db->query($query);
$row = $result->fetch_assoc();
$total['score'] = $row['sum(score)'];

// average level
$query = "SELECT avg(level) FROM spelunky_game_entry";
$result = $db->query($query);
$row = $result->fetch_assoc();
$total['level'] = $row['avg(level)'];

$query = "SELECT date, name, spelunky_game_entry.leaderboard_id, score, level, character_used FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id ORDER BY score DESC, level DESC, date ASC LIMIT 10";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) $games[] = $row;

echo "<div class=\"textbox\">\r";
echo "<p><strong>Total days played</strong>: " . number_format($total['days']) . "<br />\r";
echo "<strong>Total players</strong>: " . $total['players'] . "<br />\r";
echo "<strong>Total games played</strong>: " . number_format($total['games']) . "<br />\r";
echo "<strong>Average games per player</strong>: " . round($total['games']/$total['players'],2) . "</p>\r";

echo "<p><strong>Best score</strong>: $" . number_format($best['score']['score']) . " (" . $best['score']['name'] . ", <a href=\"" . date("/Y/m/d/",strtotime($best['score']['date'])) . "\">" . date("F j, Y",strtotime($best['score']['date'])) . "</a>)<br />\r";
echo "<strong>Total money collected</strong>: $" . number_format($total['score']) . "<br />\r";
echo "<strong>Average score</strong>: $" . number_format($total['score']/$total['games']) . "</p>\r";

echo "<p><strong>Farthest level reached</strong>: " . level($best['level']['level']) . " (" . $best['level']['name'] . ", <a href=\"" . date("/Y/m/d/",strtotime($best['level']['date'])) . "\">" . date("F j, Y",strtotime($best['level']['date'])) . "</a>)<br />\r";
echo "<strong>Average level reached</strong>: " . level($total['level']) . "</p>\r";
echo "</div>\r";
?>

<h2>Top 10 Games</h2>
<table id="scoreboard">
<tr>
<th scope="col">Date</th>
<th scope="col">Player</th>
<th scope="col">Score</th>
<th scope="col">Died on</th>
<th scope="col">Character</th>
</tr>
<?php
foreach ($games as $game) {
	echo "<tr>\r";
	echo "<td><a href=\"" . date("/Y/m/d/",strtotime($game['date'])) . "\">" . date("F j, Y",strtotime($game['date'])) . "</a></td>\r";
	echo "<td><a href=\"/stats/" . $game['name'] . "\">" . $game['name'] . "</a></td>\r";
	echo "<td>$" . number_format($game['score']) . "</td>\r";
	echo "<td>" . level($game['level']) . "</td>\r";
	echo "<td><img src=\"/images/char_" . character_icon($game['character_used']) . ".png\" /></td>\r";
	echo "</tr>\r\r";
}
echo "</table>\r\r";
?>
