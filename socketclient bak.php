<?php
date_default_timezone_set("Asia/Jakarta");
if (!defined('MSG_DONTWAIT')) define('MSG_DONTWAIT', 0x40);
error_reporting(E_ALL);
// No Timeout 
set_time_limit(0);
/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

// $address = '192.168.22.174';//IP DEV PC E-CASE
$address = '192.168.8.52';//IP DEV OLTGATEWAT
$service_port = '9099';

$app_ip = '127.1.1.0';
$app_port = '1000';

//in seconds
$time_interval = 5;


while(true)
{
    $current_date = date('H:i:s');
    $socket_up_flg=true;
    /* Create a TCP/IP socket. */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        $log= "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    }

    $result = socket_connect($socket, $address, $service_port);
    socket_set_nonblock($socket);
    if ($result === false) {
        $log = "[" . date('Y-m-d H:i:s') . "]" . " socket_connect() failed. Reason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
    } else {
        $log = "[" . date('Y-m-d H:i:s') . "]" . ' Connected to server IP : '.$address. ' Port : '.$service_port."\n";
    }
    echo $log;
    $handle = fopen('log' . date('Ymd'), "a");
    fwrite($handle, $log);
    fclose($handle);

    //Jika ada koneksi 
    if($socket && $result)
    {
        //BACA ISI FILE OUT.TXt
        while ($fp = fopen('out.txt', 'r+')) {
        
            //check apakah socket server hidup atau tidak, jika tidak keluar dari loop dan lakukan reconnect
            $next_date = date('H:i:s',strtotime($current_date)+$time_interval);
            if(date('H:i:s')==$next_date)
            {
                $current_date=date('H:i:s');
                $next_date = date('H:i:s',strtotime($current_date)+$time_interval);
                echo "Check whether server is up ".date('Y-m-d H:i:s')."\n";
                $bit_code = 'XXX160'.date('YmdHis').'';
                if(socket_write($socket,$bit_code,strlen($bit_code))===false)
                {
                    $socket_up_flg=false;
                    socket_close($socket);
                    break;
                }      
            }
           
           if($socket_up_flg)
           {
                //kirim data dari file jika ada
                while ($line = fgets($fp)) {
                    socket_write($socket, $line, strlen($line));
                }
                ftruncate($fp, 0);
                fclose($fp);
                //jika ada data yang dikirm server terima
                $buf = trim(socket_read($socket, 1024, MSG_DONTWAIT));

                //jikada ada byte yang dikirim
                if($buf)
                {
                    echo $buf."\n";
                    //Kirim byte ke insistpro
                    # Our new data
                    $data = array(
                    'data' =>$buf
                    );
                    # Create a connection
                    $url = 'http://'.$app_ip.':'.$app_port.'/insistpro/index.php?r=socket/socket/index';
                    $ch = curl_init($url);
                    # Form data string
                    $postString = http_build_query($data, '', '&');
                    # Setting our options
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    # Get the response
                    curl_exec($ch);

                    // also get the error and response code
                    $errors = curl_error($ch);
                    $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    //Write read socket to file
                    if(substr($buf,0,3) !='XXX')
                    {
                        $in = fopen('in.txt', 'a');
                        fwrite($in, $buf);
                        fwrite($in, "\r\n");
                        fclose($in); 
                    }
                }
           }        
        }
    }//end true socket & result
    echo "Trying to connect server ... \n";
}//loop connect server
echo "Socket Closed \n";