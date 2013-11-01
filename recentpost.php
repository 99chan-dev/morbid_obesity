<?php

// Database settings

$dbhost = 'nemo.regs.be'; // your host name, default is localhost
$dbuser = 'zimmy'; // your database username
$dbpass = 'cr4ck4tt4ck!'; // your database password
$dbname = 'new99'; // your database name

mysql_connect($dbhost,$dbuser,$dbpass) or die ("I could not connect!");
// Select and connect to the database
mysql_select_db($dbname) or die (mysql_error()); 

// Start the RSS Feed

header('Content-type: text/xml'); // Must declare the content type
echo '<?xml version=\'1.0\' encoding=\'UTF-8\'?>';

// Set RSS version.

echo '
<rss version=\'2.0\'> ';

// Start the XML.

echo '
<channel>
<title>Your Website</title>
<description>Description of your website</description>
<link>http://www.yourwebsite.com/</link>';

// Query database and select the last 10 entries.

$data = mysql_query("SELECT * FROM `tacobellposts` WHERE `IS_DELETED` = '0' ORDER BY `id` DESC LIMIT 15");
while($row = mysql_fetch_array($data))
{
echo '
<item>
<link>http://www.yourwebsite.com/#'.$row['boardid'].'</link>
<title>'.$row['name'].'</title>
<description>'.$row['message'].'</description>
</item>';
}

// The "while" statement loops and grabs the 
//last 10 items as we stated in our $data query.

// The <link> provides us with an anchor to the post 
//on the main page.  It is using the post ID from the database.

echo '
</channel>
</rss>';
?>
