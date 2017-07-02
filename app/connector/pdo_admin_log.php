<?php
$dbinc=array(
    'db'=>'mysql',
    'host'=>'127.0.0.1',
    'port'=>'3306',
    'dbuser'=>'root',
    'dbpw'=>'1014',
    'dbname'=>'dianjoy'
);
return new PDO($dbinc['db'].':host='.$dbinc['host'].';port='.$dbinc['port'].';dbname='.$dbinc['dbname'], $dbinc['dbuser'], $dbinc['dbpw'], array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
?>
