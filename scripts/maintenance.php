<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

header("HTTP/1.1 503 Service Unavailable", true, 503);
header("Retry-After: 3600");

// Clear the stat cache for this file
clearstatcache(true, __FILE__);
// Create a first DateTime object with current date
$updateTime = date_create();
// Create a second DateTime object to hold the file modification time
$timestamp = date_create();
// Set to the file modification time
date_timestamp_set($timestamp,filemtime(__FILE__));
// Get the differences of both
$interval = date_diff($timestamp, $updateTime);
// Format the display with full date and time of start (timestamp of file)
$dateStart = gmdate ('Y-m-d h:i:s', $timestamp->getTimestamp());
// Format the display with usual hours, minutes, seconds (don't need the day, we hope so !)
$timeElapsed = $interval->format('%hh:%im:%ss');

?><!DOCTYPE html>
<html>
<head>
	<title>Trim Maintenance Page</title>

	<style type="text/css">
		@-ms-viewport{width:device-width}article,aside,figcaption,figure,footer,header,hgroup,main,nav,section{display:block}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";font-size:1rem;font-weight:400;line-height:1.5;color:#212529;text-align:left;background-color:#fff}
		body, html {
			height: 100%;
			overflow:hidden;
		}

		.bg {
			/* The image used */
			background-image: url("trimbg-2.jpg");

			/* Full height */
			height: 100%;

			/* Center and scale the image nicely */
			background-position: center;
			background-repeat: no-repeat;
			background-size: cover;
		}
		.row{display:-ms-flexbox;display:flex;-ms-flex-wrap:wrap;flex-wrap:wrap;}
		.justify-content-center{-ms-flex-pack:center!important;justify-content:center!important}
		.align-items-center{-ms-flex-align:center!important;align-items:center!important}
		.pt-5,.py-5{padding-top:3rem!important}
		.pt-2,.py-2{padding-top:.5rem!important}
		.col,.col-1,.col-10,.col-11,.col-12,.col-2,.col-3,.col-4,.col-5,.col-6,.col-7,.col-8,.col-9,.col-auto,.col-lg,.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-lg-auto,.col-md,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,.col-md-auto,.col-sm,.col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-sm-auto,.col-xl,.col-xl-1,.col-xl-10,.col-xl-11,.col-xl-12,.col-xl-2,.col-xl-3,.col-xl-4,.col-xl-5,.col-xl-6,.col-xl-7,.col-xl-8,.col-xl-9,.col-xl-auto{position:relative;width:100%;min-height:1px;padding-right:15px;padding-left:15px}
		.col-md-12{-ms-flex:0 0 100%;flex:0 0 100%;max-width:100%}
		.text-center{text-align:center!important}
		.container{width:100%;padding-right:15px;padding-left:15px;margin-right:auto;margin-left:auto}@media (min-width:576px){.container{max-width:540px}}@media (min-width:768px){.container{max-width:720px}}@media (min-width:992px){.container{max-width:960px}}@media (min-width:1200px){.container{max-width:1140px}}
		.bg-light{background-color:#f8f9fa!important}
		.rounded{border-radius:.25rem!important}
		.border{border:1px solid #dee2e6!important}
	</style>
</head>
<body>
<div class="bg">
	<div class="row justify-content-center align-items-center pt-5">
		<img src="Ripple-1s-200px.svg"></div>
	<div class="row justify-content-center align-items-center pt-2">
		<div class="col-md-12 text-center">
			<h1>Maintenance in Progress</h1></div></div>

	<div class="col-md-12 pt-5">
		<div class="container bg-light rounded text-center p-5 border">
			<h4>
            An update started on <b><?= $dateStart; ?><i>UTC</i></b><br />
            It has progressed for <b><?= $timeElapsed; ?></b>.<br/> We regret any inconvenience, please try again in a few minutes<br>
			</h4>
		</div>
		<div class="col-md-12 text-center">
			Powered by Tiki Wiki CMS Groupware
		</div>
	</div>
</div>
</body>
</html>