<?php
require_once ("config.php"); //koneksi database
function get_http_response_code($domain1) {
  $headers = get_headers($domain1);
  return substr($headers[0], 9, 3);
}

$ip = 'IP MESIN FINGER (PUBLIC)';
$get_periode = 3; //dari hari ini sampai 3 hari kebelakang
$id_start = 1;
$id_end = 200;
#pengambilan data
$days_now = date("Y-m-d"); 
$days_before = date('Y-m-d', strtotime('-'.$get_periode.' days', strtotime($days_now)));
$number="";
    for($i=$id_start;$i<=$id_end;$i++){
      $number.=($i.",");
    }
    $number=substr($number,0,strlen($number)-1);
    $url = "http://".$ip."/form/Download?uid=".$number."&sdate=".$days_before."&edate=".$days_now."";


$get_http_response_code = get_http_response_code($url);
if ( $get_http_response_code == 200 ) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  
    $server_output = curl_exec ($ch);
  
    curl_close ($ch);

    $data = array();
    $record = explode("\n",$server_output);
    foreach($record as $r){
      $r = str_replace("\t"," ",$r);
      $isi = explode(" ",$r);
      array_push($data, $isi);
    }
    print_r($data);
    
    
?>
