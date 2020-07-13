<?php
require_once('_include.php');

//init logger
$router = null;
try{
	
	echo 'Testing';
	die;
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<script src="http://192.168.0.1/js/crypto-js.js"></script>
<script language="javascript" src="/lib/js/jquery/jquery-1.4.4.min.js"></script>
<script language="javascript">
$(document).ready(function(){
	
});

</script>

</head>
<body>



</body>
</html>
<?php 
die; ?>