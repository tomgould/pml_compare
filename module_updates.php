<?php

/**
 * @file
 *   Gets a list of the modules that have updates so you can do them individually
 */
// Paste PML Output in here
$up_output = "
Administration menu (admin_menu)                      6.x-1.8            6.x-1.8           Up to date
Advanced Poll (advpoll)                               6.x-1.x-dev        6.x-1.x-dev       Update available
Drupal                                                6.28               6.33              SECURITY UPDATE available
Chaos tools (ctools)                                  6.x-1.10           6.x-1.11          SECURITY UPDATE available
";

// how you target your site with Drush
$site = 'drush @tg';

// Print out the commands
echo "SECURITY UPDATES\n";
print_up_commands(
  $site, $up_output, 'SECURITY UPDATE'
);

echo "Update available\n";
print_up_commands(
  $site, $up_output, 'Update available'
);

/**
 * Prints out the commands needed to update your modules & core individaully
 *
 * @param string $site
 *   The string representation of how you target your site with Drush eg:
 *   - drush --uri=domain.com
 *   - drush @domain
 * @param string $up_output
 *   The output form drush pml
 *
 * @param type $string
 *   The search string to match against
 */
function print_up_commands($site, $up_output, $string) {
  $array = explode("\n", $up_output);
  foreach ($array as $line) {
    if ((strpos($line, $string)) && strpos($line, 'Locked via drush') === FALSE) {
      // Get module name from in the brackets
      $name = trim(strget($line, '(', ')'));
      if (strlen($name) == 0) {
        $name = left($line, strpos($line, ' '));
      }
      if ($name) {
        echo $site . ' up ' . $name . "\n";
      }
    }
  }
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
function strget($str, $start, $end) {
  $str = "|" . $str . "|";
  $len = mb_strlen($start);
  if (mb_strpos($str, $start) > 0) {
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
