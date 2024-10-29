<?php
error_reporting(0);

include_once("class/function.php");
include_once("class/db.php");
include_once("class/ClassMysql_i.inc.php");

$db = new Mysql_i($host, $base, $user, $pass);

global $db;

$result = array();

if(isset($_GET["action"]) && $_GET["action"] == "add" )
{
	$name = $_GET['name'];
	$phone = $_GET['phone'];
	
	if($insert_id = $db->insert("data", array("name" => $name, "phone" => $phone)))
		$result = array("error" => false, 'id' => $insert_id, 'name' => $name, 'phone' => $phone);
	else
		$result = array("error" => true);
}
else if(isset($_GET["action"]) && $_GET["action"] == "update" )
{
	$id = $_GET['id'];
	$name = $_GET['name'];
	$phone = $_GET['phone'];
	
	if($update_id = $db->update("data", array("name" => $name, "phone" => $phone), array("id" => $id)))
		$result = array("error" => false, 'id' => $update_id);
	else
		$result = array("error" => true);
}
else if(isset($_GET["action"]) && $_GET["action"] == "delete" )
{
	if($db->delete("data", array("id" => $_GET["id"])))
		$result = array("error" => false);
	else
		$result = array("error" => true);
}
else if(isset($_GET["action"]) && $_GET["action"] == "getTable")
{
	$data = $db->select("data", "", "", false, true);
	
	$result = $data;
}
else
	$result = array("error" => true);

isAjax();
echo json_encode($result);