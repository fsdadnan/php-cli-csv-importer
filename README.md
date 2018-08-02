## PHP - CSV importer (Command Line Utility) v1.0.0
Muhammad Adnan

A PHP CLI based utility to import a CSV file. You can either use the following
 CLI options to specify the settings or add your settings directly in the file.  
 
##Usage:

Example 1: `php csv-import.php --host=localhost --db=example_db --pass=secret --table=users --file=users.csv`

Example 2:`php csv-import.php --file=users.csv --table=users --truncate-table`

##Options:
`--host` DB host

`--db` DB name

`--user` DB username

`--pass` DB Password

`--file=fileName` CSV file name

`--table=tableName` Table name

`--columns=` By default the first row of the CSV file uses as the db fields name. But, you use can this `--column` parameter to define the columns from the CLI
                          :
`--truncate-table` Before importing truncate table

`--skip-rows=0` Skip number of rows

`--enclosure="\""` CSV column enclosure

`--delimiter=","` CSV delimiter

`--buffer=2500` CSV read buffer

`--batch-insert` Batch insert in database, default is FALSE

`--batch-insert=1000` Batch insert size default is 1000


## How to map CSV columns with the database fields

###Example 1: 
`--columns=csv-index-number:db-field-name,csv-index-number:db-field-name`
                          
###Example 2: You can also add extra columns which are not in the CSV
`--columns=db-column-name:value`
