<?php
error_reporting(0);

$hostname	= "localhost";
$username	= "root";
$password	= "root";
$db_name	= "EEv292";

$conn_type = 0;  // 1 = persistent    0 = non-persistent



$result = ($conn_type == 1) ? mysql_pconnect($hostname, $username, $password) : mysql_connect($hostname, $username, $password);

echo '<br />';

if ( ! $result)
{
	echo 'Unable to connect to your database server';
}
else
{
	echo 'A connection was established to your database server';
}


echo '<br /><br />';

if ( ! mysql_select_db($db_name))
{
	echo 'Unable to select your database';
}
else
{
	echo 'Your database was selected.';
}

/* End of file dbtest.php */
/* Location: ./system/expressionengine/utilities/dbtest.php */