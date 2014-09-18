pml_compare
===========

Produces CSV form Drush PML output written to files

This is very rough as I wrote it quickly to do a specific job ;)

Have a look at the CSV file in the csv directory for an example output

To use this tool you need to write the output for the sites you want to compare
to files by using drush pml eg:

$ drush --uri=demo.com pml > demo.com.txt

Do this for all the sites you want to compare then put these files somewhere
readable like the pml_output directory

When you have the files edit the pml_compare.php file to include these
file locations and the execute the script with PHP on the CLI eg:

$ cd /var/www/pml_compare
$ php pml_compare.php

This will then write a CSV to the ./csv folder

pmlModule Updates
===========

This script parses the output of "drush pml" and prints out a drush up command
for each of the projects that has an available update
