<?php

/**
 * @file
 *   Compare output from pml from different sites in a CSV
 *
 * This is very rough as I wrote it quickly to do a specific job ;)
 *
 * To use this to you need to write the output for the sites you want to compare
 * to files by using drush pml eg:
 *
 *   drush --uri=demo.com pml > demo.com.txt
 *
 * Do this for all the sites you want to compare then put these files somewhere
 * readable like the pml_output directory
 *
 * When you have the files edit the pml_compare.php file to include these
 * file locations and the execute the script with PHP on the CLI eg:
 *
 * $ cd /var/www/pml_compare
 * $ php pml_compare.php
 *
 * This will then write a CSV to the ./csv folder
 *
 */

$sites = array(
  'TomGould' => parse_pml('pml_output/tom-gould.txt'),
  'site1'    => parse_pml('pml_output/site1.com.pml.txt'),
  'site2'    => parse_pml('pml_output/site2.com.pml.txt'),
  'site3'    => parse_pml('pml_output/site3.com.pml.txt'),
);

// Make the container for the CSV and the first column data (module name)
$container = array('Headings' => array('Module'));
foreach ($sites as $site => $array) {
  foreach ($array as $module_key => $module_values) {
    $container[$module_key] = array($module_key);
  }
}
ksort($container);

// Foreach site add the sites' module data to the CSV array
foreach ($sites as $site => $array) {
  $container['Headings'][] = $site . ' Status';
  $container['Headings'][] = $site . ' Version';
  foreach ($container as $key => $value) {
    if (!empty($array[$key]) && $key !== 'Headings') {
      $container[$key][] = $array[$key]['Status'];
      $container[$key][] = $array[$key]['Version'];
    }
    else {
      if ($key !== 'Headings') {
        $container[$key][] = 'Empty';
        $container[$key][] = 'Empty';
      }
    }
  }
}

// Make a CSV
$fp = fopen('csv/module_differences.csv', 'w');
foreach ($container as $fields) {
  fputcsv($fp, $fields);
}
fclose($fp);

/**
 * Parses the PML Output read form a file into a usable array
 *
 * @param type $array
 * @return type
 */
function parse_pml($file_path) {
  // Get the PML statuses of the sitesl
  $pml = file_get_contents($file_path);

  // Explode to array of lines
  $array = explode("\n", $pml);

  // Empty arrays to hold more detialed info
  $response = array();

  // Parse the array of lines
  foreach ($array as $line) {
    $key = string_get_last($line, '(', ')');
    if ($key !== FALSE) {
      $tmp            = array_filter(explode('  ', $line));
      $columns        = array(
        'Package' => trim(array_shift($tmp)),
        'Name'    => trim(array_shift($tmp)),
        'Type'    => trim(array_shift($tmp)),
        'Status'  => trim(array_shift($tmp)),
        'Version' => trim(array_shift($tmp)),
      );
      $response[$key] = $columns;
    }
  }

  return $response;
}

/**
 * Returns text by specifying a start and end point
 * @param str $str
 *   The text to search
 * @param str $start
 *   The beginning identifier
 * @param str $end
 *   The ending identifier
 */
function string_get_last($str, $start, $end) {
  $str = "|" . $str . "|";
  $len = mb_strlen($start);
  if (mb_strpos($str, $start) > 0) {
    // Check to make sire that this is the last occurance
    if (mb_strpos(
        right($str, mb_strlen($str) - mb_strpos($str, $end)
        ), $start) > 0
    ) {
      return string_get_last(
        right($str, mb_strlen($str) - mb_strpos($str, $end) - 1), $start, $end
      );
    }
    $int_start = mb_strpos($str, $start) + $len;
    $temp      = right($str, (mb_strlen($str) - $int_start));
    $int_end   = mb_strpos($temp, $end);
    $return    = trim(left($temp, $int_end));
    return $return;
  }
  else {
    return FALSE;
  }
}

/**
 * Replacement for ASP right function
 * @param str $str
 *   the string to cut
 * @param int $count
 *   the length to cut
 */
function right($str, $count) {
  return mb_substr($str, ($count * -1));
}

/**
 * Replacement for ASP left function
 * @param str $str
 *   the string to cut
 * @param int $count
 *   the length to cut
 */
function left($str, $count) {
  return mb_substr($str, 0, $count);
}
