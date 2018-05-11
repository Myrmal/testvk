<?php
/**
 * Created by PhpStorm.
 * User: Myrmal
 * Date: 10.05.2018
 * Time: 13:25
 */
require_once ('getVkMessages.php');

$a = new getVkMessages($_POST["day"],$_POST["month"],$_POST["year"]);

$start = microtime(true);

echo
<<<HTML
<form action="" method="post">
    День:  <input type="number" name="day" size="2" min="1" max="31" value="1"/>
    Месяц: <input type="number" name="month" size="2" min="1" max="12" value="1"/>
    Год: <input type="number" name="year" size="4" min="2000" value="2018"/>
    <input type="submit" name="submit" value="Поехали!" />
</form>
HTML;

echo"<div style='border: 4px double black'>";
$a->runThis();
echo "</div>";

echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек.';
//print_r($a->runHistory());