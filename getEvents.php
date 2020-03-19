<?PHP
// Use our simple dom html parser
include 'simple_html_dom.php';
$link = $_REQUEST['url'];

$html = file_get_html($link . "/events/");

if($html === false) {
	echo "Error finding events from " . $link . "/events make sure the URL is correct";
	return;
}

$webLinks = array();

/* Display the relevant events that we found */
foreach ($html->find('tbody') as $element) {
  echo '<table class="table table-bordered">';
  echo "<thead><tr>";
  echo '<th scope="col">Event Name</th>';
  echo '<th scope="col">Event Date</th>';
  echo '<th scope="col">Select</th>';
  echo "</tr></thead>";
  $row = intval(0);
  foreach ($element->find('tr') as $entry) {
    $eventName = $entry->find('td', 0);
    $eventDate = $entry->find('td', 1);

    echo "<tr>";
    /* Remember the ID of the event, so we can get it later */
    parse_str($eventName->find('a')[0]->href, $output);

    echo '<td><input type="hidden" name="event[' . $row . '][link]" value="' . $output['id'] . '">' . $eventName->plaintext . '</td>';
    echo "<td>" . $eventDate->innertext . "</td>";
    echo '<td><input type="checkbox" class="form-check-input" name="event[' . $row . '][selected]"</td>';
    echo "</tr>";
    $row++;
  }
}
echo "</table>";
