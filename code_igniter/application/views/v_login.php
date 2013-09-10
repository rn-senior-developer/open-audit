<?php
/**
 * Open-AudIT
 *
 * An open source network auditing application
 *
 * @package Open-AudIT
 * @author Mark Unwin <mark.unwin@gmail.com>
 * @version v1.0.4
 * @copyright Copyright (c) 2013, Opmantek
 * @license http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
 */
 
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta http-equiv="Content-Script-Type" content="text/javascript" />
<meta http-equiv="Content-Style-Type" content="text/css" />
<link rel="shortcut icon" href="<?php echo base_url();?>favicon.ico" type="image/x-icon" />
	<title>Open-AudIT</title>
	<style type="text/css">
	#container { width: 950px; margin: 0 auto; padding: 10px 0; border: 1px solid #555; border-radius: 5px;}
	a { color: #101010; text-decoration: none }
	a:hover { color: #729FCF; }
	<!-- body { font-family:"Verdana","Lucida Sans Unicode","Lucida Sans",Sans-Serif; background: #dcd2bd; font-size:12px; color:#111;} -->
	body { font-family:"Verdana","Lucida Sans Unicode","Lucida Sans",Sans-Serif; background: #fff; font-size:12px; color:#111;}
	h2 { border-color:#DBD9C5; border-style:solid; border-width:0pt 0pt 1px; color:#555555; font-size:22px; font-weight:bold; padding:0px 0px 1px; }
	img {border:0;}
	</style>
</head>
<?php 
$image_path = base_url() . 'theme-tango/tango-images/';

$file_exist = '';
$filename = dirname(dirname(dirname(dirname(__FILE__)))) . "/other/audit_windows.vbs";
if (strpos($_SERVER['HTTP_USER_AGENT'], "Windows NT") === FALSE) {
	$show = "n";
} else {
	$show = "y";
}

if (!isset($logo)) {
	$logo = "logo.png";
} ?>
<body onload="document.myform.username.focus();">
	<div id="container">
	<div id="header" style='height: 200px; width: 950px; margin-left: auto; margin-right: auto; padding: 20px; border: 10px;' align='left'>
		<?php $attributes = array ('name' => 'myform'); ?>
		<?php echo form_open('login/process_login', $attributes, $hidden) . "\n"; ?>
				<div align='left' style="height: 150px; width:60%; float: left; valign: center; text-align: center;">
					<img src='<?php echo $image_path . $logo ;?>' alt='logo' border='0' /><br />
					<?php if ((file_exists($filename)) and $show == 'y') { echo "<span align=\"center\"><br /><input type=\"button\" name=\"audit\" id=\"audit\" onClick=\"audit_my_pc()\" value=\"Audit My PC\" /></span><br />&nbsp;\n"; } ?>
				</div>
				<div align='right' style="height: 150px; width:40%; float: right; text-align: center;">
					<p><label for="username">Username: </label><input type="text" name="username" id="username" size="20" /></p>
					<p><label for="password">Password: </label><input type="password" name="password" id="password" size="20" /></p>
	        		<p><?php echo form_submit('login', 'Login'); ?><?php echo $this->session->flashdata('message'); ?><br />&nbsp;</p>
				</div>
		<?php echo form_close(); ?>
		<?php if ($systems == '0' ) {
				echo "<div style=\"width: 100%; text-align: center;\"><br />&nbsp;<br />No systems, groups or reports are in the database.<br />
				Initial credentials are admin / password.<br />Please log in and change these ASAP.<br /><br /></div>\n";
			}
		?>
	</div>
<?php if (isset($oae_message)){ 
	echo "<div style='width: 950px; margin-left: auto; margin-right: auto; padding: 20px; border: 10px; text-align: center;' align='left'>\n";
	echo "\t\t<span style='font-size: 12pt;'>" . $oae_message . "<br /><br /></span>\n";
	echo "</div>\n";
}
?>
</div>
<script type="text/javascript">
	function audit_my_pc() {
		location.href = "<?php base_url(); ?>index.php/login/audit_my_pc";
	}
</script>
</body>
</html>
