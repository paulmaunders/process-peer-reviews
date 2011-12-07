#!/usr/bin/php
<?php

/* 
This script takes our annual staff surveys from a PollDaddy CSV file and works out the average peer review appraisal scores. 

- It assumes there are 2 header rows in the CSV file.
- The first header row is the list of main matrix questions (e.g. How do rate your colleagues in terms of honesty)
- The second header row is the list of sub matrix questions (e.g. Fred, Bob, John etc..)
- The scores are recorded in subsequent rows.
*/

define ('FIELD_FIRSTNAME', 7); // Index of First Name field (starting at 0)
define ('FIELD_LASTNAME', 8);// Index of Last Name field (starting at 0)

// Function taken from comments on http://php.net/manual/en/function.base-convert.php
function alpha2num($a) {
    $r = 0;
    $l = strlen($a);
    for ($i = 0; $i < $l; $i++) {
        $r += pow(26, $i) * (ord($a[$l - $i - 1]) - 0x40);
    }
    return $r - 1;
}

if (PHP_SAPI !== 'cli') {
	echo 'Please run from the command line.'. "\n";
	exit();
}

$opts = getopt("f:s:", array("file:startcolumn:"));

if ($opts['file']) $filename = $opts['filename'];
if ($opts['f']) $filename = $opts['f'];

if ($opts['startcolumn']) $startcolumn = $opts['startcolumn'];
if ($opts['s']) $startcolumn = $opts['s'];

// var_dump($opts);

if (!$filename || !$startcolumn) {
	echo 'Process peer review appraisal scores from a PollDaddy survey CSV file'."\n";
	echo ''."\n";
	echo 'Arguments:'."\n";
	echo '  --file -f              File to process (required)'."\n";
	echo '  --startcolumn -s       Starting column where first matrix answers are given, can be a letter or number (e.g. M or 12) (required)'."\n";
	exit();
} 

if (eregi('^[a-zA-Z]+$', $startcolumn)) {
	$startcolumn = alpha2num($startcolumn);
}

if (!is_numeric($startcolumn)) {
	echo 'Invalid startcolumn. Please specify either the column name (alphabetical) or it\'s numerical index'. "\n";
	exit();
} 

if (!file_exists($filename)) {
	echo 'File '.$filename.' does not exist.'."\n";
	exit();
}

$file_lines= file($filename);

$i=0;
foreach ($file_lines AS $line) {
	
	$surveys[$i++] = str_getcsv(
	$line,
	',',
	'"'
	);
	
}
	
// var_dump($surveys);

foreach ($surveys AS $sid => $survey) {

	// Get questions from main header row
	if ($sid == 0) {
		foreach ($survey AS $fid => $field) {
			if ($fid >= $startcolumn) {
				if ($field != "") {
					$last_field = $field;
					$questions[$fid] = $field;
					$questions_unique[$fid] = $field;
				} else {
					$questions[$fid] = $last_field;
				}
			}
			//if ($fid >= FIRST_MATRIX_FIELD && $field != "") $questions[$fid] = $field;
		}
	}
	
	// Get sub questions from secondary header row and setup empty answers
	if ($sid == 1) {
		foreach ($survey AS $fid => $field) {
			if ($fid >= $startcolumn && $field != "") {
				$staff_fields[$fid] = $field;
				$staff_answers_sum[$fid] = 0;
				$staff_answers_count[$fid] = 0;
				$staff_bonus_sum[$fid] = 0;
				$staff_bonus_count[$fid] = 0;
			}
		}
	}
	
	// Sum together other matrix answers from remaining rows
	if ($sid != 0 && $sid != 1) {
	
		$name = $survey[FIELD_FIRSTNAME] . ' ' . $survey[FIELD_LASTNAME];
	
		// echo $name. "\n";
	
		foreach ($survey AS $fid => $field) {
			if ($fid >= $startcolumn && $field != "") {								
				if ($staff_fields[$fid] == $name && $field != "n/a") {
					echo 'IGNORING: '. $name . ' tried to rate themselves '.$field.' for '.$questions[$fid]."\n";
				} else {				
					if (is_numeric($field)) { 
						// echo $field;
						$staff_answers_sum[$fid] += $field;
						$staff_answers_count[$fid] += 1;
					}					
					if (strpos($field, '%')) {
						// echo $field;
						//$staff_bonus_sum[$fid] += str_replace('%', '', $field);
						//$staff_bonus_count[$fid] += 1;
						$staff_answers_sum[$fid] += str_replace('%', '', $field);
						$staff_answers_count[$fid] += 1;
					}				
				}				
			}
		}
	}

}

foreach ($staff_fields AS $fid => $staff_name) {
	//if ($questions[$fid] != "") 
	//	$last_question = $questions[$fid]; 
	if ($staff_answers_count[$fid] != 0 ) 
		$staff_results[$staff_name][$questions[$fid]] = $staff_answers_sum[$fid] / $staff_answers_count[$fid];
	//if ($staff_bonus_count[$fid] != 0) 
	//	$staff_bonus[$staff_name] = $staff_bonus_sum[$fid] / $staff_bonus_count[$fid];
}

//var_dump($questions);
//var_dump($staff);
var_dump($staff_results);
//var_dump($staff_answers_sum);
//var_dump($staff_bonus);


// Output CSV

$fp = fopen("processed-".time().".csv", 'w');

// Header row
array_unshift ($questions_unique, 'Name');
fputcsv($fp, $questions_unique);
array_shift ($questions_unique);

// Results rows
foreach ($staff_results AS $name => $result) {
	$fields = array();
	$fields[] = $name;
	foreach ($questions_unique AS $question) {	
		$fields[] = $result[$question];
	}
	fputcsv($fp, $fields);
}

fclose($fp);

?>
