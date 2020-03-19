

<?php
// Use the simple dom html parser
include 'simple_html_dom.php';

class RaceDebug
{
  private static $global_debug = 0;
  public static function debug($enable, $string)
  {
    if (RaceDebug::$global_debug || $enable) {
      echo $string . "<br/>";
    }
  }
};

/* Storage class for each Type of race class */
class RaceClass
{
  private $name    = '';
  private $drivers = array();
  private $events;

  public function __construct($name)
  {
    $this->name = $name;
  }

  public function name()
  {
    return $this->name;
  }

  public function add($eventName, $driverName, $driverPoints)
  {
    if (!$this->events[$eventName]) {
      $this->events[$eventName] = array();
    }

    $this->events[$eventName][$driverName] = $driverPoints;
    if (!in_array($driverName, $this->drivers)) {
      array_push($this->drivers, $driverName);
    }
  }

  public function update($raceClass)
  {
    $this->drivers                         = array_unique(array_merge($this->drivers, $raceClass->drivers));
    $this->events[key($raceClass->events)] = $raceClass->events[key($raceClass->events)];
  }

  public function getDriverResults($name)
  {
    RaceDebug::debug(0, "Getting results for $name");
    $result = array();
    $total  = 0;
    foreach ($this->events as $key => $value) {
      if (array_key_exists($name, $value)) {
        $result[$key] = $value[$name];
        $total += intval($value[$name]);
      } else {
        $result[$key] = " - ";
      }
    }
    $result['total'] = $total;
    return $result;
  }

  public function getEvents()
  {
    return $this->events;
  }

  public function getNumberOfEvents()
  {
    return sizeof($this->events);
  }

  public function getAllDrivers()
  {
    return $this->drivers;
  }
};

function parseLinkForClasses($html)
{
  $pointsFirst  = $_POST['pointsFirst'];
  $pointsSecond = $_POST['pointsSecond'];
  $pointsThird  = $_POST['pointsThird'];
  $topQualifier = isset($_POST['topQualifier']);

  $eventDate = ltrim($html->find('.clearfix H5', 0)
      ->plaintext);
    $raceClasses = array();

  /* Web page we are scraping from holds all the classes and
  the results for a given race event in one table, so we
  find that table and interate over each class in the tabs */
  foreach ($html->find('table') as $element) {
    $rc = new RaceClass($element->find('.class_header', 0)
        ->plaintext);

      $tbody = $element->find('tbody', 0);

    $points = (int) $pointsThird;

    foreach ($tbody->find('tr') as $entry) {
      RaceDebug::debug(0, 'e = column entry, ' . $entry);
      /* Get the place the driver came, and their name */
      $place      = (int) $entry->find('td', 0)->innertext;
      $driverName = $entry->find('td', 1)->plaintext;

      if (is_numeric($place)) {
        if ($place == 1) {
          if ($topQualifier) {
            /* Top qualifier gets one extra point, this always
               seems to be the person that came first in the
               results, so i guess that's easy
            */
            $rc->add($eventDate, $driverName, $pointsFirst + 1);
          } else {
            $rc->add($eventDate, $driverName, $pointsFirst);
          }
        } else if ($place == 2) {
          $rc->add($eventDate, $driverName, $pointsSecond);
        } else {
          $rc->add($eventDate, $driverName, $points);
          $points -= 1;
          /* Protection here, if we have more drivers than points,
             then those drivers will just get 0
          */
          if ($points < 0)
            $points = 0;
        }
      }
    }
    array_push($raceClasses, $rc);
  }

  return $raceClasses;
}

/* -------------------- Main call from index ------------------ */
echo '
    <html>
      <head>
        <link rel="stylesheet" type="text/css" href="scraper.css">
      </head>
    <body>
';

if (!$_POST['pointsFirst']) {
  exit("!!!! ERROR - no first place points supplied !!!!");
}
if (!$_POST['pointsSecond']) {
  exit("!!!! ERROR - no second place points supplied !!!!");
}
if (!$_POST['pointsThird']) {
  exit("!!!! ERROR - no third place points supplied !!!!");
}

$webLinks = array();
foreach ($_POST['event'] as $event => $value) {
  if (isset($value['selected']) && $value['selected'] === 'on') {
    $html_string = $_POST['resultsPage'] . "/results/?p=view_event&id=" . $value['link'];
    $html        = file_get_html($html_string);

    foreach ($html->find('a.block') as $a) {
      if (strpos($a, "view_points")) {
        $webLinks[] = $_POST['resultsPage'] . $a->href;
      }
    }
  }
}

$html_pages = array();
/* Fetch all the web pages we want to scrap in one go
   and store them in an array to process. This is so
   at some point we can probably do a multiprocess call 
   to fetch all pages in different threads and speed up
   the process */
foreach ($webLinks as $link) {
  $html = file_get_html(htmlspecialchars_decode($link));

  /* If file_get_contents fails to get content, it returns false */
  if ($html === false) {
    /* Check that we got a valid response, otherwise echo a
       message to remind someone to upload the event */
    echo "No Race results for $link, get someone to upload it!! </br>";
  } else {
    array_push($html_pages, $html);
  }

  RaceDebug::debug(0, "Fetching page from $link");
}

$result = array();
foreach ($html_pages as $page) {
  $event = parseLinkForClasses($page);
  foreach ($event as $race) {
    if (!array_key_exists($race->name(), $result)) {
      $result[$race->name()] = $race;
    } else {
      $result[$race->name()]
        ->update($race);
    }
  }
}

/* Iterate over all the results from the different
   classes and print them out in a pretty table.
   */
foreach ($result as $class) {
  $eventCount = $class->getNumberOfEvents();

  echo '<br>';
  echo "<h3>" . $class->name() . "</h3>";
  echo '<table>';
  echo "<tr>";
  echo "<th>Driver Name</th>";

  $index = 0;
  foreach ($class->getEvents() as $key => $value) {
    echo '<th><a href="' . $webLinks[$index++] . '">' . $key . '</a></th>';
  }

  echo "<th>Total</th>";
  echo "</tr>";

  foreach ($class->getAllDrivers() as $d => $name) {
    echo "<tr>";
    $r = $class->getDriverResults($name);
    echo "<td>$name</td>";
    foreach ($r as $race => $result) {
      echo "<td>$result</td>";
    }
    echo "</tr>";
  }
  echo "</table>";
}

echo "<body></html>";
