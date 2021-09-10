<?php
$config = parse_ini_file('phpsocketconfig.ini');
date_default_timezone_set("Asia/Jakarta");
if (!defined('MSG_DONTWAIT')) define('MSG_DONTWAIT', 0x40);
error_reporting($config['debug'] ? E_ALL : 0);
// No Timeout 
set_time_limit(0);
/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

$address = $config['olt_ip'];

$service_port = $config['olt_port'];

//in seconds
$time_interval = $config['time_interval'];

// $log_folder = "";
//$log_folder = "/opt/lampp/htdocs/phpsocket/";
$log_folder = $config['log_folder'];

if (!file_exists($log_folder)) {
    mkdir($log_folder);
}

$url_app_bo = $config['bo_url_api'];
$curr_date = date('Y-m-d');

while (true) {
    //Log File Name ditaro dilooping supaya berubah keesokan harinya
    $log_file = $log_folder . 'Log' . date('Ymd') . '.log';
    $log_out_msg = $log_folder . 'MsgOut' . date('Ymd') . '.log';
    $log_in_msg  = $log_folder . 'MsgIn' . date('Ymd') . '.log';

    $current_date = date('H:i:s');
    $socket_up_flg = true;
    /* Create a TCP/IP socket. */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        $log = "110. socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    }


    $result = socket_connect($socket, $address, $service_port);


    socket_set_nonblock($socket);
    if ($result === false) {
        $log = "[" . date('Y-m-d H:i:s') . "]" . "120. socket_connect() failed. Reason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
    } else {
        $log = "[" . date('Y-m-d H:i:s') . "]" . '1. Connected to server IP : ' . $address . ' Port : ' . $service_port . "\n";
    }
    echo $log;
    $handle = fopen($log_file, "a+");
    fwrite($handle, $log);
    fclose($handle);

    //Jika ada koneksi 
    if ($socket && $result) {
        //BACA ISI FILE OUT.TXt
        while ($fp = fopen('out.txt', 'r+')) {

            //check apakah socket server hidup atau tidak, jika tidak keluar dari loop dan lakukan reconnect
            $next_date = date('H:i:s', strtotime($current_date) + $time_interval);
            if (date('H:i:s') == $next_date) {
                $current_date = date('H:i:s');
                $next_date = date('H:i:s', strtotime($current_date) + $time_interval);
                //echo "Check whether server is up ".date('Y-m-d H:i:s')."\n";
                $bit_code = 'XXX160' . date('YmdHis') . '';
                if (($ck = socket_write($socket, $bit_code, strlen($bit_code))) === false) {
                    $socket_up_flg = false;
                    //socket_close($socket);
                    socket_shutdown($socket, 2);
                    break;
                }
                echo 'Check Connection ' . date('YmdHis') . ' Length : ' . $ck . ' Message : ' . $bit_code . "\n";
            }

            if ($socket_up_flg) {

                //10.09.2021 tambah log jika beda hari socket masih up
                if (date('Y-m-d') != $curr_date) {
                    $log = "[" . date('Y-m-d H:i:s') . "]" . '2. Connected to server IP : ' . $address . ' Port : ' . $service_port . "\n";
                    echo $log;
                    $handle = fopen($log_file, "a+");
                    fwrite($handle, $log);
                    fclose($handle);
                    $curr_date = date('Y-m-d');
                }

                //kirim data dari file jika ada
                while ($line = fgets($fp)) {

                    echo 'Send Message : ' . strlen($line) . ' Message : ' . $line . "\n";

                    if (($send = socket_write($socket, $line, strlen($line))) === false) {
                        $log = "[" . date('Y-m-d H:i:s') . "]" . "130. socket_write() failed. Reason: ($result) " . socket_strerror(socket_last_error($socket));
                        $handle = fopen($log_file, "a+");
                        fwrite($handle, $log);
                        fclose($handle);
                        socket_shutdown($socket, 2); //30.08.2021
                        break;
                    } else {
                        $log = "[" . date('Y-m-d H:i:s') . "]" . 'socket_write length :' . $send . '   ' . $line;
                    }

                    $handle = fopen($log_out_msg, "a+");
                    fwrite($handle, $log);
                    fwrite($handle, "\r\n");
                    fclose($handle);
                    ftruncate($fp, 0); //reset pesan yang akan dikirim
                }

                fclose($fp);
                //jika ada data yang dikirm server terima

                //$buf = trim(socket_read($socket,"\r"));
                //Supaya semua pesan keterima dulu
                $buf = '';
                while (($currentByte = socket_read($socket, 1)) != "") {

                    if ($currentByte === FALSE) {
                        $socket_up_flg = false;
                        //socket_close($socket);
                        socket_shutdown($socket, 2);
                        break;
                    }

                    $buf .= $currentByte;
                }

                $buf = trim($buf); //hilangkan spasi depan/belakang

                //jikada ada byte yang dikirim
                if ($buf) {
                    echo 'Get Message Length : ' . strlen($buf) . ' Message : ' . $buf . "\n";
                    //Kirim byte ke insistpro
                    # Our new data
                    $data = array(
                        'data' => $buf
                    );
                    # Create a connection
                    //$url = 'http://'.$app_ip.':'.$app_port.'/insistpro/index.php?r=socket/socket/index';
                    // $url = 'http://insistpro.test/index.php?r=socket/socket/index';
                    $url = $url_app_bo;
                    $ch = curl_init($url);
                    # Form data string
                    $postString = http_build_query($data, '', '&');
                    # Setting our options
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    # Get the response
                    $exec = curl_exec($ch);
                    print_r($exec);

                    // also get the error and response code
                    $errors = curl_error($ch);
                    $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    //Write read socket to file
                    if (substr($buf, 0, 3) != 'XXX') {
                        $in = fopen($log_in_msg, 'a+');
                        $msg = '[' . date('Y-m-d H:i:s') . '] ' . ' Length : ' . strlen($buf) . '  ' . $buf;
                        fwrite($in, $msg);
                        fwrite($in, "\r\n");
                        fclose($in);
                    }
                }
            }
        }
    } //end true socket & result
    echo "Trying to connect server ... \n";
} //loop connect server
echo "Socket Closed \n";
