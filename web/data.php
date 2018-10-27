<?php

include_once("xml2php.php");

//Valid libraries and their entrances:
$library_table = [
  "AHC" => [200, "AHC Back", "AHC Front"],
  "ANTH" => [150, "ANTH"],
  "BANC" => [250, "Bancroft"],
  "BIOS" => [250, "VLSB1", "VLSB2"],
  "CHEM" => [250, "CHEM 2.9"],
  "DOE" => [500, "Doe North", "Doe South"],
  "DOE-STACKS" => [500, "Moffit Corridor, Stacks East"],
  "EAL" => [400, "EAL 2.9"],
  "EART" => [300, "Earth North", "Earth South"],
  "ENGI" => [300, "ENGI"],
  "ENVI" => [250, "ENVI"],
  "GRDS" => [200, "GRAD"],
  "MATH" => [250, "Math 2.9"],
  "MOFF" => [1000, "Moffit Entry", "Moffit Exit"],
  "MOFF-4" => [250, "Moffitt 4th"],
  "MORR" => [250, "Morrison"],
  "MUSI" => [250, "MUSI"],
  "NEWS" => [250, "News"],
  "OPTO" => [200, "OPTO"],
  "PHYS" => [300, "PHYS"],
  "PUBL" => [250, "PUBL_Front"],
  "SEAL" => [250, "SEAL"],
  "SOCR" => [250, "SOCR"],
];


$library = array_key_exists("library", $_POST) ? $_POST["library"] : null;
$start_date = array_key_exists("date", $_POST) ? $_POST["date"] : null;
$end_date = array_key_exists("to", $_POST) ? $_POST["to"] : null;
$step = array_key_exists("step", $_POST) ? $_POST["step"] : null;
$hour = array_key_exists("hour", $_POST) ? $_POST["hour"] : null;


if ($hour !== null) {
	$current_occupancies = array();
	foreach ($library_table as $name => $ent) {
    $netflow = xml_data($ent, $start_date);
    if ($netflow == "failed to read") {
      echo $netflow;
      return;
    }
    $load_dist = generateLoad($netflow[0], $netflow[1]);
    $current_occupancies[$name] = [$load_dist[(int) $hour], $ent[0]];
  }
  echo json_encode($current_occupancies);
} else if (empty($end_date)) {
  $netflow = xml_data($library_table[$library], $start_date);
  if ($netflow !== "failed to read") {
    $load_dist = generateLoad($netflow[0], $netflow[1]);

    echo implode(' ', $load_dist);
  } else {
    echo "failed to read";
  }
} else {
  $current_date = DateTime::createFromFormat('Y-m-d', $start_date);
  $to_date = DateTime::createFromFormat('Y-m-d', $end_date);
  if (array_key_exists("step", $_POST)) {
    $interval = new DateInterval("P" . $step. "D");
  } else {
    $interval = new DateInterval("P1D");
  }

  $dists = array();

  while ($current_date <= $to_date) {
    $netflow = xml_data($library_table[$library], $current_date->format("Y-m-d"));
    if ($netflow == "failed to read") {
      echo "failed to read";
      return;
    }
    $load_list = generateLoad($netflow[0], $netflow[1]);
    $formatted = $current_date->format("Y-m-d") . ": " . implode(' ', $load_list);
    array_push($dists, $formatted);
    $current_date->add($interval);
  }

  echo $library_table[$library][0] . ")" . implode(' | ', $dists);

}

$number_of_tries = 0;

function xml_data($ent_list, $date) {
	global $number_of_tries;
	$zip = new ZipArchive;
  if ($zip->open ("librarydata/HourlyTraffic-" . $date . ".xlsm") === true) {
    $zip->extractTo("./tmp/" . $date);
    $zip->close();

    $definitions_raw = XML2Array::createArray(file_get_contents("tmp/" . $date . "/xl/pivotCache/pivotCacheDefinition1.xml"));
    $records_raw = XML2Array::createArray(file_get_contents("tmp/" . $date . "/xl/pivotCache/pivotCacheRecords1.xml"))["pivotCacheRecords"]["r"];

    // Begin parsing
    $entrances_raw = $definitions_raw
                ["pivotCacheDefinition"]
                ["cacheFields"]
                ["cacheField"]
                [3]
                ["sharedItems"]
                ["s"];
    $extract_defs = function ($i) {return $i["@attributes"]["v"];};
    $all_entrances = array_map($extract_defs, $entrances_raw); // String list

    $extract_values = function ($i) {
      return [$i["n"][0]["@attributes"]["v"], $i["n"][1]["@attributes"]["v"]];
    };

    $records = array_chunk(array_map($extract_values, $records_raw), 29);

    $entrances = array();
    foreach (array_slice($ent_list, 1) as $k => $v) {
      array_push($entrances, array_search($v, $all_entrances));
    }

    $entries = array();
    $exits = array();

    foreach ($records as $key => $hour_record) {
      $entry_sum = 0; $exit_sum = 0;
      foreach ($entrances as $key => $entrance) {
        $entry_sum += $hour_record[$entrance][0];
        $exit_sum += $hour_record[$entrance][1];
      }
      array_push($entries, $entry_sum);
      array_push($exits, $exit_sum);
    }
    $number_of_tries = 0;

    return [$entries, $exits];

  } else if ($number_of_tries < 5) {
    $number_of_tries += 1;
    $interval = new DateInterval("P7D");
    $new = DateTime::createFromFormat("Y-m-d", $date);
    $new->sub($interval);
    return xml_data($ent_list, $new->format("Y-m-d"));
  } else {
    $number_of_tries = 0;
    return "failed to read";
  }
}


function generateLoad($net_in, $net_out) {
  $load = [$net_in[0] - $net_out[0]];
  foreach (range(1, 23) as $hour) {
    array_push($load, $load[count($load) - 1] + $net_in[$hour] - $net_out[$hour]);
  }
  $min_load = min($load);
  if ($min_load < 0) {
    $mod_load = array();
    foreach (range(0, 23) as $i) {
      array_push($mod_load, -$min_load + $load[$i]);
    }
    return $mod_load;
  } else {
    return $load;
  }
}

// Extraction algorithm for XML
/*
function library_data_from_xml($name, $date) {
  $zip = new ZipArchive; // zip opener
  if ($zip->open ("librarydata/HourlyTraffic-" . $date . ".xlsm") === true) {
    $zip->extractTo("./tmp/" . $date);
    $zip->close();

    // File reading
    $xml = file_get_contents("tmp/" . $date . "/xl/charts/chart1.xml");
    $file_data = XML2Array::createArray($xml)
      ['c:chartSpace']
      ['c:chart']
      ['c:plotArea']
      ['c:lineChart']
      ['c:ser'];
    // echo json_encode(array_keys($file_data));
    // echo json_encode($file_data['0']);

    $entries = array(); // Records the # of entries on the given date
    $exits = array(); // Records the # of entrances on the given date


    $select = function ($i) {return (int) $i['c:v'];}; // Converts raw data into integer arrays

    switch ($name) {
      case "AHC":
        $entry_b = array_map($select, $file_data[0]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_b = array_map($select, $file_data[1]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $entry_f = array_map($select, $file_data[2]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_f = array_map($select, $file_data[3]['c:val']['c:numRef']['c:numCache']['c:pt']);
        foreach (range(0, count($entry_b) - 1) as $i) {
          array_push($entries, $entry_b[$i] + $entry_f[$i]);
          array_push($exits, $exit_b[$i] + $exit_f[$i]);
        }
        break;
      case "ANTH":
        $entries = array_map($select, $file_data[4]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[5]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "BANC":
        $entries = array_map($select, $file_data[6]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[7]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "BIOS":
        $entry_1 = array_map($select, $file_data[8]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_1 = array_map($select, $file_data[9]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $entry_2 = array_map($select, $file_data[10]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_2 = array_map($select, $file_data[11]['c:val']['c:numRef']['c:numCache']['c:pt']);
        foreach (range(0, count($entry_1) - 1) as $i) {
          array_push($entries, $entry_1[$i] + $entry_2[$i]);
          array_push($exits, $exit_1[$i] + $exit_2[$i]);
        }
        break;
      case "CHEM":
        $entries = array_map($select, $file_data[12]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[13]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "DOE":
        $entry_n = array_map($select, $file_data[14]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_n = array_map($select, $file_data[15]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $entry_s = array_map($select, $file_data[16]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_s = array_map($select, $file_data[17]['c:val']['c:numRef']['c:numCache']['c:pt']);
        foreach (range(0, count($entry_n) - 1) as $i) {
          array_push($entries, $entry_n[$i] + $entry_s[$i]);
          array_push($exits, $exit_n[$i] + $exit_s[$i]);
        }
        break;
      case "DOE-STACKS":
        $entry_1 = array_map($select, $file_data[18]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_1 = array_map($select, $file_data[19]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $entry_2 = array_map($select, $file_data[20]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_2 = array_map($select, $file_data[21]['c:val']['c:numRef']['c:numCache']['c:pt']);
        foreach (range(0, count($entry_1) - 1) as $i) {
          array_push($entries, $entry_1[$i] + $entry_2[$i]);
          array_push($exits, $exit_1[$i] + $exit_2[$i]);
        }
        break;
      case "EAL":
        $entries = array_map($select, $file_data[22]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[23]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "EART":
        $entry_n = array_map($select, $file_data[24]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_n = array_map($select, $file_data[25]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $entry_s = array_map($select, $file_data[26]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_s = array_map($select, $file_data[27]['c:val']['c:numRef']['c:numCache']['c:pt']);
        foreach (range(0, count($entry_n) - 1) as $i) {
          array_push($entries, $entry_n[$i] + $entry_s[$i]);
          array_push($exits, $exit_n[$i] + $exit_s[$i]);
        }
        break;
      case "ENGR":
        $entries = array_map($select, $file_data[28]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[29]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "ENVI":
        $entries = array_map($select, $file_data[30]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[31]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "GRDS":
        $entries = array_map($select, $file_data[32]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[33]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "MATH":
        $entries = array_map($select, $file_data[34]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[35]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "MOFF":
        $entry_1 = array_map($select, $file_data[36]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_1 = array_map($select, $file_data[37]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $entry_2 = array_map($select, $file_data[38]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exit_2 = array_map($select, $file_data[39]['c:val']['c:numRef']['c:numCache']['c:pt']);
        foreach (range(0, count($entry_1) - 1) as $i) {
          array_push($entries, $entry_1[$i] + $entry_2[$i]);
          array_push($exits, $exit_1[$i] + $exit_2[$i]);
        }
        break;
      case "MOFF-4":
        $entries = array_map($select, $file_data[40]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[41]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "MORR":
        $entries = array_map($select, $file_data[42]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[43]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "MUSI":
        $entries = array_map($select, $file_data[44]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[45]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "NEWS":
        $entries = array_map($select, $file_data[46]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[47]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "OPTO":
        $entries = array_map($select, $file_data[48]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[49]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "PHYS":
        $entries = array_map($select, $file_data[50]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[51]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "PUBL":
        $entries = array_map($select, $file_data[52]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[53]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "SEAL":
        $entries = array_map($select, $file_data[54]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[55]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      case "SOCR":
        $entries = array_map($select, $file_data[56]['c:val']['c:numRef']['c:numCache']['c:pt']);
        $exits = array_map($select, $file_data[57]['c:val']['c:numRef']['c:numCache']['c:pt']);
        break;
      default:
        echo "library not found: " . $name . "\n";
        echo json_encode($file_data[4]['c:val']['c:numRef']['c:numCache']['c:pt']);
    }


    return [$entries, $exits];

  } else {
    return "failed to read";
  }
}
*/


 ?>
