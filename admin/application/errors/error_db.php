<?php
// Global fallback: this template renders whenever $this->db->query() fails
// and db_debug is on (DB_driver.php ~line 325). The real error (number,
// message, full SQL) is already logged via log_message('error', ...) by
// the caller before this file is ever included — nothing diagnostic is
// lost by not echoing it here. This is the last line of defense against
// raw SQL/schema leaking to the browser; it does not replace doing a
// proper pre-check (duplicate/stale-FK/etc.) in the controller/model —
// see stabilization/10_bespoke_masters_hardening.md for that pattern.

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($is_ajax) {
	if (!headers_sent()) {
		header('Content-Type: application/json');
	}
	echo json_encode(array(
		'status'  => false,
		'success' => false,
		'message' => 'Unable to proceed the requested process. Please try again, or contact support if the issue persists.',
	));
	return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Something Went Wrong</title>
<style type="text/css">

::selection{ background-color: #E13300; color: white; }
::moz-selection{ background-color: #E13300; color: white; }
::webkit-selection{ background-color: #E13300; color: white; }

body {
	background-color: #fff;
	margin: 40px;
	font: 13px/20px normal Helvetica, Arial, sans-serif;
	color: #4F5155;
}

a {
	color: #003399;
	background-color: transparent;
	font-weight: normal;
}

h1 {
	color: #444;
	background-color: transparent;
	border-bottom: 1px solid #D0D0D0;
	font-size: 19px;
	font-weight: normal;
	margin: 0 0 14px 0;
	padding: 14px 15px 10px 15px;
}

#container {
	margin: 10px;
	border: 1px solid #D0D0D0;
	-webkit-box-shadow: 0 0 8px #D0D0D0;
}

p {
	margin: 12px 15px 12px 15px;
}
</style>
</head>
<body>
	<div id="container">
		<h1>Something Went Wrong</h1>
		<p>Unable to proceed the requested process. Please try again, or contact support if the issue persists.</p>
	</div>
</body>
</html>
