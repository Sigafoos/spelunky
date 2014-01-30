<!doctype html>
<html>
<head>
<title>Player stats for <?php echo $_GET['player']; ?></title
</head>
<body>
<?php
if (!$_GET['player']) header("Location:index.php");

require('functions.php');

$query = "SELECT date, spelunky_game_entry.leaderboard_id, score, level, character_used FROM spelunky_game_entry INNER JOIN spelunky_players ON spelunky_game_entry.steamid=spelunky_players.steamid INNER JOIN spelunky_games ON spelunky_game_entry.leaderboard_id=spelunky_games.leaderboard_id WHERE lower(name)='" . addslashes(strtolower($_GET['player'])) . "' ORDER BY date ASC";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
	if ($row['score'] === "0") continue; // let's not be mean
	$games[] = $row;
	$characters[$row['character_used']]++;
	if (!$best || $best['score'] < $row['score']) $best = $row;
	//if (!$farthest || $best['score'] < $row['score']) $best = $row;
	$scores[] = $row['score'];
}
arsort($characters);

echo "<h1>Player stats for " . $_GET['player'] . "</h1>";
echo "<p><strong>Games played</strong>: " . count($games) . "<br />\r";
echo "<strong>First game</strong>: " . date("F j, Y",strtotime($games[0]['date'])) . " ($" . number_format($games[0]['score']) . ")<br />\r";
echo "<strong>Latest game</strong>: " . date("F j, Y",strtotime($games[count($games)-1]['date'])) . " ($" . number_format($games[count($games)-1]['score']) . ")</p>\r";

echo "<p><strong>Best score</strong>: $" . number_format($best['score']) . " (" . date("F j, Y",strtotime($best['date'])) . ")<br />\r";
echo "<strong>Average score</strong>: $" . number_format(round(array_sum($scores) / count($scores))) . "</p>";

echo "<p><strong>Favorite character</strong> (" . current($characters) . " times): <img src=\"images/char_" . character_icon(key($characters)) . ".png\" /></p>\r";

echo "<h2>Game history</h2>";
rsort($games);
foreach ($games as $game) {
	echo "<h3><a href=\"/?date=" . date("Y-m-d",strtotime($game['date'])) . "\">" . date("F j, Y",strtotime($game['date'])) . "</a></h3>\r";
	echo "<p><img src=\"images/char_" . character_icon($game['character_used']) . ".png\" /><br />\r";
	echo "<strong>Score</strong>: $" . number_format($game['score']) . "<br />\r";
	echo "<strong>Died on</strong>: " . $game['level'] . "</p>\r";
}
?>
</body>
</html>
