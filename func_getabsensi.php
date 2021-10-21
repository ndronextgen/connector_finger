<?php
require_once ("../../wz_config/config.php");
function get_http_response_code($domain1) {
  $headers = get_headers($domain1);
  return substr($headers[0], 9, 3);
}
$id_unic=$_POST["id_unic"];
#definisi
$cek_event=mysql_query("SHOW VARIABLES WHERE VARIABLE_NAME = 'event_scheduler'");$cek_list=mysql_fetch_array($cek_event);
$hasil_cek_event = $cek_list['1'];
if ($hasil_cek_event == 'OFF'){ $do_on = mysql_query("SET GLOBAL event_scheduler = ON"); } else {  }
$sql_query=mysql_query("SELECT
                            tbl_ip.id_unic,
                            tbl_ip.ip,
                            tbl_ip.ideselon,
                            tbl_ip.active,
                            tbl_ip.get_number,
                            tbl_ip.max_number,
                            tbl_ip.get_user,
                            tbl_groupip.nama_eselon,
                            tbl_groupip.tbl_name,
                            tbl_ip.get_periode
                            FROM
                            tbl_ip
                            INNER JOIN tbl_groupip ON tbl_ip.ideselon = tbl_groupip.ideselon WHERE id_unic = '$id_unic'");
$list=mysql_fetch_array($sql_query);
$ip = $list['ip'];
$tbl_nama = $list['tbl_name'];
$get_periode = $list['get_periode'];
$number_start = $list['get_number'] + 1;
$number_end = $list['get_number']+$list['get_user'];
if ($list['max_number'] <= $number_end) { $start = '0'; } else { $start = $number_end; }
#pengambilan data
$days_now = date("Y-m-d"); 
$days_before = date('Y-m-d', strtotime('-'.$get_periode.' days', strtotime($days_now)));
$number="";
    for($i=$number_start;$i<=$number_end;$i++){
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
    //print_r($data);
    #verifikasi jumlah data yg masuk
    $jml_row = 0;$jml_row_error=0;
    for ($row = 0; $row <= count($data)-1; $row++) {
        if(count($data[$row])== '6'){
            $userid     = $data[$row][0];
            $cekinout    = $data[$row][2].' '.$data[$row][3];
            $tgl = $data[$row][2];
            $jam = $data[$row][3];
          // --
          $date_sub=date_create($tgl);
          date_sub($date_sub,date_interval_create_from_date_string("1 days"));
          $Date_Min = date_format($date_sub,"Y-m-d");
          // --
          $sql_cek="SELECT cekinout FROM $tbl_nama where cekinout='$cekinout' and userid='$userid'";
          $qry_cek=mysql_query($sql_cek);
          $jum_cek=mysql_num_rows($qry_cek);
          if ($jum_cek < 1 AND $userid !='') {

            #cek shif/tugas
            $sql_cek_batas="SELECT
                              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                              start_in,end_in,start_out,end_out
                            FROM
                              absensi_log
                            LEFT JOIN
                            (
                              SELECT master_shift.Shift,
                                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                              FROM  master_shift
                            ) AS o ON o.Shift = absensi_log.Shift
                            WHERE absensi_log.FingerBadgeNumber = '$userid' AND absensi_log.Tanggal = '$tgl' 
                            AND absensi_log.AturanPulang < '23:59:59'";
            $qry_cek_batas=mysql_query($sql_cek_batas);
            $jum_cek_batas=mysql_fetch_array($qry_cek_batas);
            $start_in = $jum_cek_batas['start_in'];$start_out = $jum_cek_batas['start_out'];
            $end_in = $jum_cek_batas['end_in'];$end_out = $jum_cek_batas['end_out'];

              if ($jam >='$start_in' AND $jam <= '$end_in'){ $status = 'in'; } elseif ($jam > '$start_out' AND $jam <= '$end_out') { $status = 'out'; } else { $status='NA'; }
                  $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='$status'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
          } else {
            $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NO'");
          }
          //shift
          $sql_cek_malam="SELECT cekinout FROM tbl_cekinout_ppnpn where cekinout='$cekinout' and userid='$userid'";
          $qry_cek_malam=mysql_query($sql_cek_malam);
          $jum_cek_malam=mysql_num_rows($qry_cek_malam);
          if ($jum_cek_malam < 1 AND $userid !='') {
            $sql_cek_absensi_in = mysql_num_rows(mysql_query("SELECT
                        absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                        start_in,end_in,start_out,end_out
                      FROM
                        absensi_log
                      LEFT JOIN
                      (
                        SELECT master_shift.Shift,
                          substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                          substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                        FROM  master_shift
                        WHERE master_shift.KeyMalam = '1'
                      ) AS o ON o.Shift = absensi_log.Shift
                      WHERE absensi_log.FingerBadgeNumber = '$userid'
                      AND Tanggal = '$tgl' AND start_in < '$jam' AND end_in > '$jam'"));

              $sql_cek_absensi_out = mysql_num_rows(mysql_query("SELECT
              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
              start_in,end_in,start_out,end_out
              FROM
              absensi_log
              LEFT JOIN
              (
              SELECT master_shift.Shift,
                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
              FROM  master_shift
              WHERE master_shift.KeyMalam = '1'
              ) AS o ON o.Shift = absensi_log.Shift
              WHERE absensi_log.FingerBadgeNumber = '$userid'
              AND Tanggal = '$Date_Min' AND start_out < '$jam' AND end_out > '$jam'"));
            //kalo absensi now ada sesuai jam batasnya in 
            if($sql_cek_absensi_in > 0){
              //in
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='in'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
            } else if ($sql_cek_absensi_out > 0){
              //out
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$Date_Min',
                                                     jam='$jam',
                                                     status='out'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }

            } else {
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NI'");
            }
          }

        } elseif(count($data[$row])== '7'){
          $userid     = $data[$row][0];
          $cekinout    = $data[$row][3].' '.$data[$row][4];
          $tgl = $data[$row][3];
          $jam = $data[$row][4];
          // --
          $date_sub=date_create($tgl);
          date_sub($date_sub,date_interval_create_from_date_string("1 days"));
          $Date_Min = date_format($date_sub,"Y-m-d");
          // --
          $sql_cek="SELECT cekinout FROM $tbl_nama where cekinout='$cekinout' and userid='$userid'";
          $qry_cek=mysql_query($sql_cek);
          $jum_cek=mysql_num_rows($qry_cek);
          if ($jum_cek < 1 AND $userid !='') {

            #cek shif/tugas
            $sql_cek_batas="SELECT
                              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                              start_in,end_in,start_out,end_out
                            FROM
                              absensi_log
                            LEFT JOIN
                            (
                              SELECT master_shift.Shift,
                                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                              FROM  master_shift
                            ) AS o ON o.Shift = absensi_log.Shift
                            WHERE absensi_log.FingerBadgeNumber = '$userid' AND absensi_log.Tanggal = '$tgl' 
                            AND absensi_log.AturanPulang < '23:59:59'";
            $qry_cek_batas=mysql_query($sql_cek_batas);
            $jum_cek_batas=mysql_fetch_array($qry_cek_batas);
            $start_in = $jum_cek_batas['start_in'];$start_out = $jum_cek_batas['start_out'];
            $end_in = $jum_cek_batas['end_in'];$end_out = $jum_cek_batas['end_out'];

              if ($jam >='$start_in' AND $jam <= '$end_in'){ $status = 'in'; } elseif ($jam > '$start_out' AND $jam <= '$end_out') { $status = 'out'; } else { $status='NA'; }
                  $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='$status'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
          } else {
            $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NO'");
          }
          //shift
          $sql_cek_malam="SELECT cekinout FROM tbl_cekinout_ppnpn where cekinout='$cekinout' and userid='$userid'";
          $qry_cek_malam=mysql_query($sql_cek_malam);
          $jum_cek_malam=mysql_num_rows($qry_cek_malam);
          if ($jum_cek_malam < 1 AND $userid !='') {
            $sql_cek_absensi_in = mysql_num_rows(mysql_query("SELECT
                        absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                        start_in,end_in,start_out,end_out
                      FROM
                        absensi_log
                      LEFT JOIN
                      (
                        SELECT master_shift.Shift,
                          substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                          substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                        FROM  master_shift
                        WHERE master_shift.KeyMalam = '1'
                      ) AS o ON o.Shift = absensi_log.Shift
                      WHERE absensi_log.FingerBadgeNumber = '$userid'
                      AND Tanggal = '$tgl' AND start_in < '$jam' AND end_in > '$jam'"));

              $sql_cek_absensi_out = mysql_num_rows(mysql_query("SELECT
              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
              start_in,end_in,start_out,end_out
              FROM
              absensi_log
              LEFT JOIN
              (
              SELECT master_shift.Shift,
                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
              FROM  master_shift
              WHERE master_shift.KeyMalam = '1'
              ) AS o ON o.Shift = absensi_log.Shift
              WHERE absensi_log.FingerBadgeNumber = '$userid'
              AND Tanggal = '$Date_Min' AND start_out < '$jam' AND end_out > '$jam'"));
            //kalo absensi now ada sesuai jam batasnya in 
            if($sql_cek_absensi_in > 0){
              //in
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='in'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
            } else if ($sql_cek_absensi_out > 0){
              //out
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$Date_Min',
                                                     jam='$jam',
                                                     status='out'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }

            } else {
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NI'");
            }
          }
          
        } elseif(count($data[$row])== '8'){
            $userid     = $data[$row][0];
            $cekinout    = $data[$row][4].' '.$data[$row][5];
            $tgl = $data[$row][4];
            $jam = $data[$row][5];
            // --
          $date_sub=date_create($tgl);
          date_sub($date_sub,date_interval_create_from_date_string("1 days"));
          $Date_Min = date_format($date_sub,"Y-m-d");
          // --
          $sql_cek="SELECT cekinout FROM $tbl_nama where cekinout='$cekinout' and userid='$userid'";
          $qry_cek=mysql_query($sql_cek);
          $jum_cek=mysql_num_rows($qry_cek);
          if ($jum_cek < 1 AND $userid !='') {

            #cek shif/tugas
            $sql_cek_batas="SELECT
                              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                              start_in,end_in,start_out,end_out
                            FROM
                              absensi_log
                            LEFT JOIN
                            (
                              SELECT master_shift.Shift,
                                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                              FROM  master_shift
                            ) AS o ON o.Shift = absensi_log.Shift
                            WHERE absensi_log.FingerBadgeNumber = '$userid' AND absensi_log.Tanggal = '$tgl' 
                            AND absensi_log.AturanPulang < '23:59:59'";
            $qry_cek_batas=mysql_query($sql_cek_batas);
            $jum_cek_batas=mysql_fetch_array($qry_cek_batas);
            $start_in = $jum_cek_batas['start_in'];$start_out = $jum_cek_batas['start_out'];
            $end_in = $jum_cek_batas['end_in'];$end_out = $jum_cek_batas['end_out'];

              if ($jam >='$start_in' AND $jam <= '$end_in'){ $status = 'in'; } elseif ($jam > '$start_out' AND $jam <= '$end_out') { $status = 'out'; } else { $status='NA'; }
                  $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='$status'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
          } else {
            $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NO'");
          }
          //shift
          $sql_cek_malam="SELECT cekinout FROM tbl_cekinout_ppnpn where cekinout='$cekinout' and userid='$userid'";
          $qry_cek_malam=mysql_query($sql_cek_malam);
          $jum_cek_malam=mysql_num_rows($qry_cek_malam);
          if ($jum_cek_malam < 1 AND $userid !='') {
            $sql_cek_absensi_in = mysql_num_rows(mysql_query("SELECT
                        absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                        start_in,end_in,start_out,end_out
                      FROM
                        absensi_log
                      LEFT JOIN
                      (
                        SELECT master_shift.Shift,
                          substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                          substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                        FROM  master_shift
                        WHERE master_shift.KeyMalam = '1'
                      ) AS o ON o.Shift = absensi_log.Shift
                      WHERE absensi_log.FingerBadgeNumber = '$userid'
                      AND Tanggal = '$tgl' AND start_in < '$jam' AND end_in > '$jam'"));

              $sql_cek_absensi_out = mysql_num_rows(mysql_query("SELECT
              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
              start_in,end_in,start_out,end_out
              FROM
              absensi_log
              LEFT JOIN
              (
              SELECT master_shift.Shift,
                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
              FROM  master_shift
              WHERE master_shift.KeyMalam = '1'
              ) AS o ON o.Shift = absensi_log.Shift
              WHERE absensi_log.FingerBadgeNumber = '$userid'
              AND Tanggal = '$Date_Min' AND start_out < '$jam' AND end_out > '$jam'"));
            //kalo absensi now ada sesuai jam batasnya in 
            if($sql_cek_absensi_in > 0){
              //in
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='in'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
            } else if ($sql_cek_absensi_out > 0){
              //out
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$Date_Min',
                                                     jam='$jam',
                                                     status='out'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }

            } else {
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NI'");
            }
          }
        } elseif(count($data[$row])== '9'){
            $userid     = $data[$row][0];
            $cekinout    = $data[$row][5].' '.$data[$row][6];
            $tgl = $data[$row][5];
            $jam = $data[$row][6];
            // --
          $date_sub=date_create($tgl);
          date_sub($date_sub,date_interval_create_from_date_string("1 days"));
          $Date_Min = date_format($date_sub,"Y-m-d");
          // --
          $sql_cek="SELECT cekinout FROM $tbl_nama where cekinout='$cekinout' and userid='$userid'";
          $qry_cek=mysql_query($sql_cek);
          $jum_cek=mysql_num_rows($qry_cek);
          if ($jum_cek < 1 AND $userid !='') {

            #cek shif/tugas
            $sql_cek_batas="SELECT
                              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                              start_in,end_in,start_out,end_out
                            FROM
                              absensi_log
                            LEFT JOIN
                            (
                              SELECT master_shift.Shift,
                                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                              FROM  master_shift
                            ) AS o ON o.Shift = absensi_log.Shift
                            WHERE absensi_log.FingerBadgeNumber = '$userid' AND absensi_log.Tanggal = '$tgl' 
                            AND absensi_log.AturanPulang < '23:59:59'";
            $qry_cek_batas=mysql_query($sql_cek_batas);
            $jum_cek_batas=mysql_fetch_array($qry_cek_batas);
            $start_in = $jum_cek_batas['start_in'];$start_out = $jum_cek_batas['start_out'];
            $end_in = $jum_cek_batas['end_in'];$end_out = $jum_cek_batas['end_out'];

              if ($jam >='$start_in' AND $jam <= '$end_in'){ $status = 'in'; } elseif ($jam > '$start_out' AND $jam <= '$end_out') { $status = 'out'; } else { $status='NA'; }
                  $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='$status'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
          } else {
            $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NO'");
          }
          //shift
          $sql_cek_malam="SELECT cekinout FROM tbl_cekinout_ppnpn where cekinout='$cekinout' and userid='$userid'";
          $qry_cek_malam=mysql_query($sql_cek_malam);
          $jum_cek_malam=mysql_num_rows($qry_cek_malam);
          if ($jum_cek_malam < 1 AND $userid !='') {
            $sql_cek_absensi_in = mysql_num_rows(mysql_query("SELECT
                        absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                        start_in,end_in,start_out,end_out
                      FROM
                        absensi_log
                      LEFT JOIN
                      (
                        SELECT master_shift.Shift,
                          substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                          substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                        FROM  master_shift
                        WHERE master_shift.KeyMalam = '1'
                      ) AS o ON o.Shift = absensi_log.Shift
                      WHERE absensi_log.FingerBadgeNumber = '$userid'
                      AND Tanggal = '$tgl' AND start_in < '$jam' AND end_in > '$jam'"));

              $sql_cek_absensi_out = mysql_num_rows(mysql_query("SELECT
              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
              start_in,end_in,start_out,end_out
              FROM
              absensi_log
              LEFT JOIN
              (
              SELECT master_shift.Shift,
                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
              FROM  master_shift
              WHERE master_shift.KeyMalam = '1'
              ) AS o ON o.Shift = absensi_log.Shift
              WHERE absensi_log.FingerBadgeNumber = '$userid'
              AND Tanggal = '$Date_Min' AND start_out < '$jam' AND end_out > '$jam'"));
            //kalo absensi now ada sesuai jam batasnya in 
            if($sql_cek_absensi_in > 0){
              //in
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='in'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
            } else if ($sql_cek_absensi_out > 0){
              //out
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$Date_Min',
                                                     jam='$jam',
                                                     status='out'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }

            } else {
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NI'");
            }
          }
        } elseif(count($data[$row])== '10'){
            $userid     = $data[$row][0];
            $cekinout    = $data[$row][6].' '.$data[$row][7];
            $tgl = $data[$row][6];
            $jam = $data[$row][7];
            // --
          $date_sub=date_create($tgl);
          date_sub($date_sub,date_interval_create_from_date_string("1 days"));
          $Date_Min = date_format($date_sub,"Y-m-d");
          // --
          $sql_cek="SELECT cekinout FROM $tbl_nama where cekinout='$cekinout' and userid='$userid'";
          $qry_cek=mysql_query($sql_cek);
          $jum_cek=mysql_num_rows($qry_cek);
          if ($jum_cek < 1 AND $userid !='') {

            #cek shif/tugas
            $sql_cek_batas="SELECT
                              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                              start_in,end_in,start_out,end_out
                            FROM
                              absensi_log
                            LEFT JOIN
                            (
                              SELECT master_shift.Shift,
                                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                              FROM  master_shift
                            ) AS o ON o.Shift = absensi_log.Shift
                            WHERE absensi_log.FingerBadgeNumber = '$userid' AND absensi_log.Tanggal = '$tgl' 
                            AND absensi_log.AturanPulang < '23:59:59'";
            $qry_cek_batas=mysql_query($sql_cek_batas);
            $jum_cek_batas=mysql_fetch_array($qry_cek_batas);
            $start_in = $jum_cek_batas['start_in'];$start_out = $jum_cek_batas['start_out'];
            $end_in = $jum_cek_batas['end_in'];$end_out = $jum_cek_batas['end_out'];

              if ($jam >='$start_in' AND $jam <= '$end_in'){ $status = 'in'; } elseif ($jam > '$start_out' AND $jam <= '$end_out') { $status = 'out'; } else { $status='NA'; }
                  $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='$status'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
          } else {
            $sql_insert = mysql_query("INSERT INTO $tbl_nama SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NO'");
          }
          //shift
          $sql_cek_malam="SELECT cekinout FROM tbl_cekinout_ppnpn where cekinout='$cekinout' and userid='$userid'";
          $qry_cek_malam=mysql_query($sql_cek_malam);
          $jum_cek_malam=mysql_num_rows($qry_cek_malam);
          if ($jum_cek_malam < 1 AND $userid !='') {
            $sql_cek_absensi_in = mysql_num_rows(mysql_query("SELECT
                        absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
                        start_in,end_in,start_out,end_out
                      FROM
                        absensi_log
                      LEFT JOIN
                      (
                        SELECT master_shift.Shift,
                          substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                          substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
                        FROM  master_shift
                        WHERE master_shift.KeyMalam = '1'
                      ) AS o ON o.Shift = absensi_log.Shift
                      WHERE absensi_log.FingerBadgeNumber = '$userid'
                      AND Tanggal = '$tgl' AND start_in < '$jam' AND end_in > '$jam'"));

              $sql_cek_absensi_out = mysql_num_rows(mysql_query("SELECT
              absensi_log.FingerBadgeNumber, absensi_log.Tanggal, absensi_log.Shift,
              start_in,end_in,start_out,end_out
              FROM
              absensi_log
              LEFT JOIN
              (
              SELECT master_shift.Shift,
                substr(master_shift.BatasMasuk,1,8) as start_in, substr(master_shift.BatasMasuk,10,8) as end_in,
                substr(master_shift.BatasPulang,1,8) as start_out, substr(master_shift.BatasPulang,10,8) as end_out
              FROM  master_shift
              WHERE master_shift.KeyMalam = '1'
              ) AS o ON o.Shift = absensi_log.Shift
              WHERE absensi_log.FingerBadgeNumber = '$userid'
              AND Tanggal = '$Date_Min' AND start_out < '$jam' AND end_out > '$jam'"));
            //kalo absensi now ada sesuai jam batasnya in 
            if($sql_cek_absensi_in > 0){
              //in
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='in'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }
            } else if ($sql_cek_absensi_out > 0){
              //out
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$Date_Min',
                                                     jam='$jam',
                                                     status='out'");
              if($sql_insert){ $jml_row++; } else { $jml_row_error++; }

            } else {
              $sql_insert = mysql_query("INSERT INTO tbl_cekinout_ppnpn SET 
                                                     userid='$userid',
                                                     cekinout='$cekinout',
                                                     tgl='$tgl',
                                                     jam='$jam',
                                                     status='NI'");
            }
          }
        }
    }

        $sql_update = mysql_query("UPDATE tbl_ip SET get_number='$start' WHERE id_unic = '$id_unic'");   
        echo '<code>'.$jml_row.' Row Affected |'.$jml_row_error.' Row Error</code>';

} else {
  echo "<kbd>Get Data Error</kbd>";
}
    
?>
