<?php

/**** Configuration ****/

// The KML file you want to process
$kml_file = 'dataset.kml';

// The name of the table to create in the SQL file
$table_name = 'dataset';

// Whether to escape the ampersands in the KML; shp2kml doesn't do it
// Change only if you're having problems
$auto_escape = true;


/***************************/
/**** Meat and Potatoes ****/
/***************************/

$kml = file_get_contents($kml_file);

if ($auto_escape)
  $kml = str_replace('&', '&amp;', $kml);

$places_xml = simplexml_load_string($kml);
$headings = array('NAME', 'LONGITUDE', 'LATITUDE');
$headings_mapped = false;
$data = array();

foreach ($places_xml->Document->Folder[0]->Placemark as $place) {
  $coords = explode(',', trim($place->Point->coordinates));
  $row = array(
    $place->name
    , $coords[0]
    , $coords[1]
  );

  $desc_bits = explode('</tr>', $place->description);
  array_pop($desc_bits);
  array_shift($desc_bits);

  foreach ($desc_bits as $item) {
    if (empty($item))
      continue;

    $item_bits = explode('</td>', $item);
    $item_head = trim(strip_tags($item_bits[0]));
    $item_value = trim(strip_tags($item_bits[1]));

    if (!$headings_mapped) {
      if (strtolower($item_head) != 'name')
        $headings[] = $item_head;
    }

    if (strtolower($item_head) != 'name')
      $row[] = $item_value;
  }

  $headings_mapped = true;
  $data[] = $row;
}

$output = '';

$output = 'CREATE TABLE IF NOT EXISTS `' . $table_name . "` (\n";
$output .= "\t`id` int(11) NOT NULL AUTO_INCREMENT,\n";
$output .= "\t`name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,\n";
$output .= "\t`lat` double NOT NULL,\n";
$output .= "\t`lng` double NOT NULL,\n";
$fields = array();

foreach (array_slice($headings, 3) as $head) {
  $output .= "\t`" . $head . "` text COLLATE utf8_unicode_ci NOT NULL,\n";
  $fields[] = '`' . $head . '`';
}

$output .= "\tPRIMARY KEY (`id`)\n";
$output .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;\n\n";

$output .= 'INSERT INTO `' . $table_name . '` (`name`, `lat` ,`lng`, ' . implode(', ', $fields) . ") VALUES\n";
$values = array();

foreach ($data as $row) {
  $values[] = '("' . implode('", "', $row) . '")';
}

$output .= implode(",\n", $values);
$output .= ";\n";

file_put_contents(str_replace('.kml', '.sql', $kml_file), $output);
