<?php

header("HTTP/1.1 503 Service Unavailable", true, 503);
header("Retry-After: 3600");

?>
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
 <head>
 <title>Maintenance in progress</title>
 </head>
 <body>
  <h1>Maintenance in progress</h1>
  <p>An update is currently in progress on this website. Please try again in a few minutes.</p>
 </body>
</html>
