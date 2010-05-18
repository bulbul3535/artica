<?php
session_start();
include_once('ressources/class.templates.inc');
include_once('ressources/class.users.menus.inc');
include_once('ressources/class.openvpn.inc');
include_once('ressources/class.system.network.inc');
include_once('ressources/class.mysql.inc');
$users=new usersMenus();
if(!$users->AsSystemAdministrator){die("alert('no access');");}


if(isset($_GET["popup"])){popup();exit;}
if(isset($_GET["iframe"])){echo up_iframe();exit;}
if( isset($_POST['upload']) ){Configloaded();exit();}
if(isset($_GET["master-list"])){echo masters_list();exit;}
if(isset($_GET["enable-row"])){echo masters_list_enable();exit;}
if(isset($_GET["view-id"])){echo master_edit();exit;}
if(isset($_GET["save-row"])){master_save();exit;}

js();



function js(){
	
	$page=CurrentPageName();
	$tpl=new templates();
	$OPENVPN_SERVER_CONNECT=$tpl->_ENGINE_parse_body('{OPENVPN_SERVER_CONNECT}');

	
	$html="
		function OpenVPNServerStart(){
			YahooWin4(500,'$page?popup=yes','$OPENVPN_SERVER_CONNECT');
			}
			
		function RefreshOpenVPNMasterList(){
		LoadAjax('master-list','$page?master-list=yes');
		}
			
		function RemoteClientVPNDelete(id){
			LoadAjax('master-list','$page?master-list=yes&delete='+id);
		}
		
		function EditVpnClientID(id){
			YahooWin5(500,'$page?view-id='+id,'$OPENVPN_SERVER_CONNECT');
		}
		
		
	var x_OPenVPNServerEnable=function (obj) {
		var results=obj.responseText;
		if (results.length>0){alert(results);}
		RefreshOpenVPNMasterList();
		}		
				
		
		function OPenVPNServerEnable(id){
			var XHR = new XHRConnection();
			XHR.appendData('enable-row',id);
			XHR.appendData('value',document.getElementById(id).value);
			XHR.sendAndLoad('$page', 'GET',x_OPenVPNServerEnable);
							
		}
		
	var x_EditVpnClientIDSave=function (obj) {
		var results=obj.responseText;
		if (results.length>0){alert(results);}
		RefreshOpenVPNMasterList();
		YahooWin5Hide();
		}		
		
		function EditVpnClientIDSave(id){
			var XHR = new XHRConnection();
			XHR.appendData('save-row',id);
			XHR.appendData('ethlisten',document.getElementById('ethlisten').value);
			document.getElementById('editclientvpn').innerHTML='<center><img src=\"img/wait_verybig.gif\"></center>';
			XHR.sendAndLoad('$page', 'GET',x_EditVpnClientIDSave);
			
		}


	
	OpenVPNServerStart();
	";
	
	echo $html;
	
}

function master_save(){
	
	$sql="UPDATE vpnclient SET ethlisten='{$_GET["ethlisten"]}' WHERE ID={$_GET["save-row"]}";
	$q=new mysql();
	$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){
		echo $q->mysql_error;
	}
	$sock=new sockets();
	$sock->getFrameWork("openvpn.php?restart-clients=yes");
	
}


function master_edit(){
		$sql="SELECT ID,ethlisten,connexion_name FROM vpnclient WHERE connexion_type=2 AND ID={$_GET["view-id"]}";
		$q=new mysql();
		$ARRR=@mysql_fetch_array($q->QUERY_SQL($sql,"artica_backup"));	
		
$nic=new networking();
while (list ($num, $ligne) = each ($nic->array_TCP) ){
	if($ligne==null){continue;}
	$ipeth[$num]="$num ($ligne)"; 
	}

	
$ipeth[null]="{none}";
$IPTABLES_ETH=Field_array_Hash($ipeth,'ethlisten',$ARRR["ethlisten"]);	
		
	$html="
	<H1>{$ARRR["connexion_name"]}</H1>
	<div id='editclientvpn'>
	<table class=table_form>
		<tr>
		<td class=legend>{openvpn_access_interface}:</td>
		<td>$IPTABLES_ETH</td>
		<td>".help_icon("{openvpn_access_interface_text}")."</td>
	</tr>
	<tr>
		<td colspan=3 align='right'><hr><input type='button' OnClick=\"javascript:EditVpnClientIDSave('{$_GET["view-id"]}');\" value='{edit}&nbsp;&raquo;&raquo;'></td>
	</table>
	</div>
	";

		$tpl=new templates();
	echo $tpl->_ENGINE_parse_body($html,"users.openvpn.index.php");
	
		
	
}

function popup(){
	$page=CurrentPageName();
	$html="
	<H1>{OPENVPN_SERVER_CONNECT}</H1>
<p class=caption>{OPENVPN_SERVER_CONNECT_TEXT}</p>
<p class=caption>{OPENVPN_SERVER_CONNECT_EXPLAIN}</p>
<div style='width:100%;text-align:right;padding:4px'><input type='button' OnClick=\"RefreshOpenVPNMasterList();\" value='{refresh}&nbsp;&raquo;'></div>
<div style='width:100%;height:150px;overflow:auto' id='master-list'>". masters_list()."</div>
". RoundedLightWhite("
<iframe src='$page?iframe=yes' style='width:100%;height:120px;border:0px'></iframe>

");

$tpl=new templates();
echo $tpl->_ENGINE_parse_body($html,"index.openvpn.php");	
}

function masters_list(){
	$q=new mysql();
	if(isset($_GET["delete"])){
		$sql="DELETE FROM vpnclient WHERE ID={$_GET["delete"]}";
		$q->QUERY_SQL($sql,"artica_backup");
		if($q->ok){
			$sock=new sockets();
			$sock->getFrameWork("openvpn.php?restart-clients=yes");
		}
	}
	
	$sql="SELECT ID,enabled,servername,serverport,connexion_name,connexion_type,routes FROM vpnclient WHERE connexion_type=2 ORDER BY ID DESC";
	
	$results=$q->QUERY_SQL($sql,"artica_backup");
	$html="<table style='width:99%'>";
	$sock=new sockets();

	while($ligne=mysql_fetch_array($results,MYSQL_ASSOC)){
			//$js="EditVPNRemoteSite('{$ligne["ID"]}');";
			//$jsDownload="VPNRemoteSiteConfig('{$ligne["ID"]}');";
			
			$running=$sock->getFrameWork("openvpn.php?is-client-running={$ligne["ID"]}");
			if($running=="TRUE"){
				$img_running="status_service_run.png";
			}else{
				$img_running="status_service_removed.png";
			}
			$enabled=Field_numeric_checkbox_img("enabled_{$ligne["ID"]}",$ligne["enabled"],"{enable_disable}","OPenVPNServerEnable");
		
			$html=$html. "
			<tr ". CellRollOver($js).">
			<td width=1%><img src='img/fw_bold.gif'></td>
			<td width=1%>tun{$ligne["ID"]}</td>
			<td width=1%><img src='img/$img_running'></td>
			<td nowrap>{$ligne["connexion_name"]}</td>
			<td nowrap>{$ligne["servername"]}</td>
			<td nowrap>{$ligne["serverport"]}</td>
			<td nowrap><input type=button OnClick=\"javascript:EditVpnClientID('{$ligne["ID"]}');\" value='{edit}'></td>
			<td nowrap>$enabled</td>
			<td width=1%>". imgtootltip("ed_delete.gif","{delete}","RemoteClientVPNDelete('{$ligne["ID"]}')")."</td>
			</tr>
		
			";
		
		}	
		
	$html=$html."</table>";
	
	$html=RoundedLightWhite($html);
	$tpl=new templates();
	return $tpl->_ENGINE_parse_body($html,"users.openvpn.index.php");
	
}


function up_iframe($error=null){
$page=CurrentPageName();
$html="
<div style='color:red;font-size:12px;font-family:arial'>$error</div>
<form method=\"post\" enctype=\"multipart/form-data\" action=\"$page\">
<p>
<input type=\"file\" name=\"fichier\" size=\"30\">
<hr>
<div style='text-align:right'>
<input type='submit' name='upload' value='{upload file}&nbsp;&raquo;' style='width:90px'>
</div>
</p>
</form>";	
	$tpl=new templates();
	echo iframe($tpl->_ENGINE_parse_body($html,"index.openvpn.php"),0,0);
	
}

function Configloaded(){
	
	if(!is_file('/usr/bin/unzip')){
		up_iframe("ERROR: unable to stat \"/usr/bin/unzip\", please advise your Administrator");
		exit;	
	}
	
	$tmp_file = $_FILES['fichier']['tmp_name'];
	$content_dir=dirname(__FILE__)."/ressources/conf/upload";
	if(!is_dir($content_dir)){mkdir($content_dir);}
	if( !is_uploaded_file($tmp_file) ){up_iframe('{error_unable_to_upload_file}');exit();}
	$type_file = $_FILES['fichier']['type'];
	if( !strstr($type_file, 'application/zip')){	up_iframe('{error_file_extension_not_match} :'.$type_file.' did not match application/zip');	exit();}
	$name_file = $_FILES['fichier']['name'];
	$ext=file_ext($name_file);
	if( !strstr($ext, 'zip')){	up_iframe('{error_file_extension_not_match} :.'.$ext.' did not match .zip');exit();}
	if(file_exists( $content_dir . "/" .$name_file)){@unlink( $content_dir . "/" .$name_file);}
 	$script_file=$content_dir . "/" .$name_file;
	if( !move_uploaded_file($tmp_file, $script_file) ){up_iframe("{error_unable_to_move_file} : $tmp_file");exit();}
    
	shell_exec("/usr/bin/unzip -j -o $script_file -d $content_dir/ >$content_dir/unzip.txt 2>&1");
	$output=explode("\n",@file_get_contents("$content_dir/unzip.txt"));
	$export=implode("<br>",$output);
	
	
	
	

	$handle=opendir($content_dir);
	$f=false;
	while (false !== ($file = readdir($handle))) {
		if(preg_match("#(.+?).ovpn$#",$file)){
		$export=$export .implode("<br>",import_ovpn("$content_dir/$file"));
		$f=true;
		}
	}
	
	if(!$f){
		$res[]="unable to find ovpn file...";
	}
	
	if(is_array($res)){
		$export=$export .implode("<br>",$res);
	}
	$export=str_replace("$content_dir/","",$export);
	up_iframe($export);
}

function import_ovpn($filepath){
	$log=array();
	$dir=dirname($filepath);
	
	$datas=explode("\n",@file_get_contents($filepath));
	
	while (list ($num, $val) = each ($datas) ){
		if(preg_match("#^remote\s+(.+?)\s+([0-9]+)#",$val,$re)){
			$server=$re[1];
			$port=$re[2];
		}
		
		if(preg_match("#^ca\s+(.+)#",$val,$re)){
			$re[1]=trim($re[1]);
			$ca_path="$dir/{$re[1]}";
		}
		
		if(preg_match("#^cert\s+(.+)#",$val,$re)){
			$re[1]=trim($re[1]);$cert_path="$dir/{$re[1]}";
		}

		if(preg_match("#^key\s+(.+)#",$val,$re)){
			$re[1]=trim($re[1]);$key_path="$dir/{$re[1]}";
		}			
		
		if(preg_match("#ip-win32#",$val)){
			unset($datas[$num]);
		}
		
		if(preg_match("#\#ROUTE\s+([0-9\.]+)\s+([0-9\.]+)#",$val,$re)){
			$routes[]="{$re[1]}\t{$re[2]}";
		}
		
	}	
	
	@file_put_contents($filepath,implode("\n",$datas));
	
	if(($server==null) && ($port==null)){$log[]="missing server and port, it is not a openvpn artica script!";}
	if(!is_file($ca_path)){$log[]="unable to stat &laquo;$ca_path&raquo;";}
	if(!is_file($cert_path)){$log[]="unable to stat &laquo;$cert_path&raquo;";}
	if(!is_file($key_path)){$log[]="unable to stat &laquo;$key_path&raquo;";}	
	if(count($log)>0){return $log;}
	
	$q=new mysql();
	
	
	$ca=$q->ReadBinaryFile($ca_path);
	$cert=$q->ReadBinaryFile($cert_path);
	$key=$q->ReadBinaryFile($key_path);
	$ovpn=$q->ReadBinaryFile($filepath);
	
	if(is_array($routes)){
		$rr=implode("\n",$routes);
	}
	
	$sql="SELECT ID FROM vpnclient WHERE servername='$server' AND serverport='$port' AND connexion_type=2";
	$ligne=@mysql_fetch_array($q->QUERY_SQL($sql,"artica_backup"));	
	$ID=$ligne["ID"];
	
	$sql_add="INSERT INTO vpnclient (servername,serverport,connexion_name,connexion_type,ca_bin,key_bin,cert_bin,ovpn,routes,enabled)
	VALUES('$server','$port','$server:$port',2,'$ca','$key','$cert','$ovpn','$rr',1);";
	
	$sql_edit="UPDATE vpnclient SET ca_bin='$ca',key_bin='$key',cert_bin='$cert',ovpn='$ovpn',routes='$rr' WHERE ID=$ID;";
	
	if($ID>0){
		$q->QUERY_SQL($sql_edit,"artica_backup");
	}else{
		$q->QUERY_SQL($sql_add,"artica_backup");
	}
	if(!$q->ok){
		$log[]=$q->mysql_error;
		return $log;
	}
	
	$log[]="{success}";
	@unlink($ca_path);
	@unlink($cert_path);
	@unlink($key_path);
	@unlink($filepath);
	
	$sock=new sockets();
	$sock->getFrameWork("openvpn.php?restart-clients=yes");
	
	return $log;
}

function masters_list_enable(){
	if(!preg_match("#enabled_([0-9]+)#",$_GET["enable-row"],$re)){return null;}
	$id=$re[1];
	$value=$_GET["value"];
	
	$sql="UPDATE vpnclient SET enabled=$value WHERE ID=$id";
	$q=new mysql();
	$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){echo $q->mysql_error;}else{
		$sock=new sockets();
		$sock->getFrameWork("openvpn.php?restart-clients=yes");
	}
	
	
	
	
	
}


?>