<?php

define('DATABASE', 'DATABASE');
define('DB_SERVER', 'localhost');
define('DB_USER', 'postgres');
define('DB_PASS', 'password');

/* No Need to edit below here */


error_reporting(E_ALL); 
ini_set('display_errors', '1');

require_once('adodb5/adodb.inc.php');

$GLOBALS['dbConn'] = NewADOConnection('postgres');
$GLOBALS['dbConn']->Connect(DB_SERVER, DB_USER, DB_PASS, DATABASE);

function getListOfTables()
{
    $results = $GLOBALS['dbConn']->Execute("SELECT relname, n_tup_ins - n_tup_del as rowcount FROM pg_stat_all_tables WHERE relname !~ '^pg_' AND relname !~ '^sql_' ORDER BY relname;");
    $tables = array();
    
    foreach ($results->GetRows() as $row)
    {
        $tables[$row['relname']] = array(
            'columns' => 0,
            'indexed_columns' => 0,
            'records' => $row['rowcount'],
            'date_created' => '',
            'last_modified' => '',
        );
    }
    
    return $tables;
}

function analyzeTable($tableName)
{
    $returnArray = array();
    
    // Update Column Count
    $results = $GLOBALS['dbConn']->Execute("select count(*) from information_schema.columns where table_name='{$tableName}'; ");
    $results = $results->GetRows();
    $returnArray['columns'] = $results[0]['count'];
    
    // Update Column Indices Count
    $query = "SELECT  relname FROM pg_class WHERE oid IN
    (SELECT indexrelid FROM pg_index, pg_class
       WHERE pg_class.relname='{$tableName}'
        AND pg_class.oid=pg_index.indrelid
    )";
    $results = $GLOBALS['dbConn']->Execute($query);
    $results = $results->GetRows();
    $returnArray['indexed_columns'] = count($results);
    
    return $returnArray;
}

$tables = getListOfTables();

foreach ($tables as $tableName=>&$info)
{
    $info = array_merge($info, analyzeTable($tableName));
}

$numTablesWithIssues = 0;

?>
<!DOCTYPE html>

<html>
<head>
    <title>Analysis for Database: <?php echo DATABASE; ?></title>
    <style type="text/css">
        td.hasIssue {
            background: red;
            color: #FFF;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Analysis for Database: <?php echo DATABASE; ?></h1>
    <p>Tables are flagged if:</p>
    <ul>
        <li>Table has no records</li>
        <li>Table has only one column</li>
        <li>Table has no indices</li>
        <li>Less than 20% of columns are indexed (Minor)</li>
    </ul>
    <table border="1" cellpadding="3">
        <thead>
            <th>Table Name</th>
            <th># Records</th>
            <th># Columns</th>
            <th># Indexed Columns</th>
            <th>% Indexed Columns</th>
            <th>Has Issue</th>
        </thead>
        <?php
        
        foreach ($tables as $tableName=>$info)
        {
            $tableHasIssue = FALSE;
        ?>
        <tr>
            <td><?php echo $tableName; ?></td>
            <td class="<?php if ($info['records'] == 0) { echo "hasIssue"; $tableHasIssue = TRUE; } ?>"><?php echo $info['records']; ?></td>
            <td class="<?php if ($info['columns'] == 1) { echo "hasIssue"; $tableHasIssue = TRUE; } ?>"><?php echo $info['columns']; ?></td>
            <td class="<?php if ($info['indexed_columns'] == 0) { echo "hasIssue"; $tableHasIssue = TRUE; } ?>"><?php echo $info['indexed_columns']; ?></td>
            <?php $percIndexedColumns = round($info['indexed_columns']/$info['columns']*100); ?>
            <td class="<?php
                if ($percIndexedColumns < 20) {
                    echo "hasIssue";
                    //$tableHasIssue = TRUE;
                }
                ?>">
                <?php echo $percIndexedColumns ?>%
            </td>
            <?php
                if ($tableHasIssue) {
                    $numTablesWithIssues++;
            ?>
                <td class="hasIssue">YES</td>
            <?php } else  { ?>
                <td>No</td>
            <?php } ?>
            
        </tr>
        <?php } ?>
    </table>

    <p>Number of Tables: <?php echo count($tables); ?></p>
    <p>Number of Tables with Issues: <?php echo $numTablesWithIssues; ?></p>
    
</body>
</html>