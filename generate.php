<?php
/**
 * Created by PhpStorm.
 * User: gaotao
 * Date: 18/2/19
 * Time: 下午4:07
 */

include_once './vendor/autoload.php';

$opt = getopt("h:p:d:u:k:");

if (sizeof($opt) != 5) {
    echo "Usage: php generate.php [options]" . PHP_EOL;
    echo "-h host 地址" . PHP_EOL;
    echo "-p port 端口" . PHP_EOL;
    echo "-d database 数据库" . PHP_EOL;
    echo "-u user 用户名" . PHP_EOL;
    echo "-k pwd 密码" . PHP_EOL;
    exit;
}

$dsn = "mysql:dbname={$opt['d']};host={$opt['h']};port={$opt['p']}";
$db = new PDO($dsn, $opt['u'], $opt['k']);
$db->query("SET NAMES utf8");

$file = "./dictionary/databaseDictionary_" . date('YmdHis') . ".md";
$fileHandle = fopen($file, "wb+");
$fileContent = "";

$fileContent .= "#{$opt['d']}数据库字典\n";
$fileContent .= "##数据表总览\n";

// 数据库总览
$sql = <<<sql
SELECT
	substring_index(TABLE_NAME , '_' , 2) AS d1 ,
	substring_index(TABLE_NAME , '_' , - 1) AS d2 ,
	information_schema. TABLES .*
FROM
	information_schema. TABLES
WHERE
	TABLE_SCHEMA = '{$opt['d']}'
ORDER BY
	d1 ASC ,
	d2 + 0 ASC
sql;
$queryHandle = $db->query($sql);
$tables = $queryHandle->fetchAll(PDO::FETCH_CLASS);


$progressBar = new \ProgressBar\Manager(0, sizeof($tables));
$progressBar->setFormat('生成数据表总览 : %current%/%max% [%bar%] %percent%% %eta%');

$fileContent .= "|TABLE_SCHEMA|TABLE_NAME|ENGINE|CREATE_TIME|TABLE_COLLATION|TABLE_COMMENT|\n";
$fileContent .= "|------------|:-----|:-----|:-----|:-----|:-----|:-----|\n";
foreach ($tables as $table) {
    foreach ($table as &$item) {
        $item = str_escape($item, '/_([a-z0-9]+)/', '\\\$0');
    }
    unset($item);
    $fileContent .= "|{$table->TABLE_SCHEMA}|{$table->TABLE_NAME}|{$table->ENGINE}|{$table->CREATE_TIME}|{$table->TABLE_COLLATION}|{$table->TABLE_COMMENT}|\n";
    $progressBar->advance();
}

$progressBar = new \ProgressBar\Manager(0, sizeof($tables));
$progressBar->setFormat('生成数据表详情 : %current%/%max% [%bar%] %percent%% %eta%');

// 数据表详情
$fileContent .= "##数据表详情\n";

foreach ($tables as $table) {
    $fileContent .= "###{$table->TABLE_NAME}\n";
    $fileContent .= "|字段名|数据类型|默认值|允许为空|PK|注释|\n";
    $fileContent .= "|-----|:-----|:----|:----:|:----|:----|\n";
    $tableName = str_replace('\\', '', $table->TABLE_NAME);
    $sql = <<<sql
SELECT
    C.COLUMN_NAME AS COLUMN_NAME,
    C.COLUMN_TYPE AS COLUMN_TYPE,
    C.COLUMN_DEFAULT AS COLUMN_DEFAULT,
    C.IS_NULLABLE AS IS_NULLABLE,
    C.EXTRA AS PK,
    C.COLUMN_COMMENT AS COLUMN_COMMENT
FROM
    information_schema.COLUMNS C
INNER JOIN information_schema.TABLES T ON C.TABLE_SCHEMA = T.TABLE_SCHEMA
AND C.TABLE_NAME = T.TABLE_NAME
WHERE
    T.TABLE_SCHEMA = 'im' and T.TABLE_NAME='{$tableName}'
sql;
    $queryHandle = $db->query($sql);
    $columns = $queryHandle->fetchAll(PDO::FETCH_CLASS);
    foreach ($columns as $column) {
        foreach($column as &$item){
            $item = str_escape($item, '/_([a-z0-9]+)/', '\\\$0');
        }
        unset($item);
        $fileContent .= "|{$column->COLUMN_NAME}|{$column->COLUMN_TYPE}|{$column->COLUMN_DEFAULT}|{$column->IS_NULLABLE}|{$column->PK}|{$column->COLUMN_COMMENT}|\n";
    }
    $progressBar->advance();
}

fwrite($fileHandle, $fileContent);
fclose($fileHandle);

function str_escape($str, $escape, $replace)
{
    if (preg_match($escape, $str)) {
        $str = preg_replace($escape, $replace, $str);
    }
    return $str;
}