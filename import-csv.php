<?php
define('SCRIPT_START', microtime(true));

/*
 * -----------------------------------------------------------------------------
 * PHP CSV importer - command line utility
 * Version  : 1.0.0
 * Author   : Muhammad Adnan (adnan@fsdsolutions.com)
 * Company  : FSD Solutions
 * -----------------------------------------------------------------------------
 *
 * A PHP command line script to import a CSV file. You can either use following
 * options from CLI or specified settings under settings' section or mix of it.
 *
 * Usage:
 * php csv-import.php --host=localhost --db=example_db --pass=secret --table=users --file=users.csv
 * php csv-import.php --file=users.csv --table=users --truncate-table
 *
 *
 * Options:
 * --host                   : db host
 * --db                     : db name
 * --user                   : db username
 * --pass                   : db password
 
 * --file=fileName          : CSV file name
 * --table=tableName        : Table name
 * --columns=               : By default CSV file's column names of first row uses as table
 *                          : fields, otherwise use this parameter to define columns
 *                          : mapping with fields:
 *                          : --columns=csv-index-number:column-name,csv-index-number:column-name
 *                          :
 *                          : You can also add extra columns which are not in the CSV
 *                          : --columns=db-column-name:value
 *                          :
 * --truncate-table         : Before importing truncate table
 * --skip-rows=0            : Skip number of rows
 * --enclosure="\""         : CSV column enclosure
 * --delimiter=","          : CSV delimiter
 * --buffer=2500            : CSV read buffer
 * --batch-insert           : Batch insert in database, default is FALSE
 * --batch-insert=1000      : Batch insert size default is 1000
 */

// --------------------------------------------
// Settings
// --------------------------------------------
$host = '127.0.0.1:33060';
$db = 'fsdcamp';
$user = 'homestead';
$pass = 'secret';
$table = null;
$csvFile = null;
$params = [];
$columns = [];
$delimiter = ",";
$enclosure = "\"";
$skipRows = 0;
$buffer = 2500;
$truncateTable = false;
$batchInsert = false;
$batchSize = 1000;
$inColumns = null;
$otherColumns = [];


// --------------------------------------------
// PARSING PARAMETERS
// --------------------------------------------
$host = getParam('--host', $host);
$db = getParam('--db', $db);
$user = getParam('--user', $user);
$pass = getParam('--pass', $pass);
$csvFile = getParam('--file', $csvFile);
$table = getParam('--table', $table);
$skipRows = getParam('--skip-rows', $skipRows);
$enclosure = getParam('--enclosure', $enclosure);
$delimiter = getParam('--delimiter', $delimiter);
$buffer = getParam('--buffer', $buffer);
$truncateTable = getParam('--truncate-table', $truncateTable);
$batchInsert = getParam('--batch-insert', $batchInsert);
$inColumns = getParam('--columns', $inColumns);

// --------------------------------------------
// PARSING PARAMETERS
// --------------------------------------------
$skipRows = $skipRows < 0 ? 0 : $skipRows;
$buffer = $buffer < 0 ? 1000 : $buffer;

if( $batchInsert && gettype($batchInsert) !== 'boolean' ) {
	$batchSize = ((int) $batchInsert ) < 0 ? $batchSize : $batchInsert;
}

if( $inColumns ) {
	$inColumns = explode(",", $inColumns );
	foreach( $inColumns as $inColumn ) {
		$parts = explode(":", $inColumn);
		if( count($parts) !== 2) {
			die('--columns parameter is not correctly specified. Example: --columns=0:id,1=name,2=email etc.');
		}
		
		if ( is_numeric($parts[0]) )
			$columns[ $parts[0] ] = $parts[1];
		else
			$otherColumns[ $parts[0] ] = $parts[1];
	}
}

// --------------------------------------------
// PROCESSING
// --------------------------------------------
if(!$fp = fopen($csvFile, 'r')) {
	die('Unable to read CSV file: ' . $csvFile);
}

if ( $truncateTable ) {
	query("TRUNCATE " . $table);
}

$nRow = 0;
$values = [];
$sql = '';
$batchCount = 0;
$init = false;
while(($row = fgetcsv($fp, $buffer, ",")) !== false) {
	
	
	// if first row and no column is specified then look at first row for the column names.
	if ($nRow++ == 0 && !$columns ) {
		$columns = $row;
		continue;
	}
	
	// skipping csv rows
	if ( $nRow <= $skipRows ) {
		continue;
	}
	
	if ( !$init ) {
		$columnNames = array_merge( array_values($columns), array_keys($otherColumns) );
		$sql = "INSERT INTO " . $table . '(' . implode(",", array_values($columnNames)) . ') VALUES ';
		$values = [];
		$init = true;
	}
	
	$fields = [];
	foreach($row as $value ) {
		$fields[] = '"' . $value . '"';
	}
	
	foreach($otherColumns as $value) {
		$fields[] = '"' . $value . '"';
	}
	
	$values[] = '('. implode(",", $fields) .')';
	
	if ( !$batchInsert ) {
		$sql = $sql . implode(',', $values);
		query($sql);
		$init = false;
		continue;
	}
	
	if ( $batchCount++ >= $batchSize ) {
		$batchCount = 0;
		query($sql . implode(',', $values));
		$init = false;
	}
}

if( $init ) {
	query($sql . implode(',', $values));
}
fclose($fp);


define('SCRIPT_END', microtime(true));
dd( "CSV has imported successfully in " . number_format(SCRIPT_END- SCRIPT_START, 4) . ' seconds.' );

// --------------------------------------------
// FUNCTIONS
// --------------------------------------------
function getConnection() {
	static $mysqli;
	if(is_null($mysqli)) {
		global $host, $db, $user, $pass;
		$mysqli = new mysqli($host, $user, $pass, $db);
		if($mysqli->connect_errno) {
			die ("Unable to establish connection with db. Error: " . $mysqli->connect_error);
		}
	}
	
	return $mysqli;
}

function query($sql) {
	return getConnection()->query($sql) or trigger_error("*** SQL Query Failed ***\n> SQL: $sql\n> Error: " . mysqli_error( getConnection() ), E_USER_ERROR);
}

function querySelect($sql, $assoc = false) {
	
	if(!$result = getConnection()->query($sql)) {
		return [];
	}
	
	$rows[] = null;
	if($assoc) {
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$result->free();
	}
	else {
		$result->fetch_all();
		$result->free();
	}
	
	return $rows;
}

function getParam( $name, $defaultValue=null) {
	global $argv;
	static $params;
	if ( !$params ) {
		foreach($argv as $i => $param) {
			
			// ignoring first para which is a file name
			if($i == 0) {
				continue;
			}
			
			$parts = explode("=", $param);
			if ( count($parts) == 2) {
				$params[ $parts[0] ] = $parts[1];
			} else {
				$params[ $parts[0] ] = true;
			}
		}
	}
	
	if( !array_key_exists( $name, $params)) {
		return $defaultValue;
	}
	
	return $params[$name];
}

function dd($data, $exit=0) {
	echo print_r($data, true) . "\n";
	if ( $exit )
		die();
}
