<?php 
/* NOTES on Irongeek's TorOrNot script:
To use this php script on some pages it will need it to have a png extension.
To do this, put a redirect from a png file to the php file in your apache config file (httpd.conf) or .htaccess. 
Example line:
Redirect /torornot.png /torornot.php

Consider this code to be GPLed, but I'd love for you to email me at Irongeek (at) irongeek.com with any changes you make. 
More information about using php and images can be found at http://us3.php.net/manual/en/ref.image.php
More information on detecting Tor exit nodes with TorDNSEL see https://www.torproject.org/tordnsel/

Adrian Crenshaw
http://www.irongeek.com
*/
//header("Content-type: image/png");
header("Content-type: text/html");
if (IsTorExitPoint()) {
print("tor");
}else{
die("this is only a test");
}
function IsTorExitPoint(){
if 
(gethostbyname(ReverseIPOctets($_SERVER['REMOTE_ADDR']).".".$_SERVER['SERVER_PORT'].".".ReverseIPOctets($_SERVER['SERVER_ADDR']).".ip-port.exitlist.torproject.org")=="127.0.0.2") 
{
return true;
} else {
return false;
} 
}
function ReverseIPOctets($inputip){
$ipoc = explode(".",$inputip);
return $ipoc[3].".".$ipoc[2].".".$ipoc[1].".".$ipoc[0];
}
?> 

