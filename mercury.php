<?php

// https://github.com/mrkrasser/MercuryStats/blob/master/Readme.ru.md
// https://github.com/mrkrasser/MercuryStats/blob/master/examples/MercuryStatsGetCurrent.xively.php

// Script expects 4 env. variables:
// SERVER_HOST - ip address or host name of influxdb
// SERVER_PORT - influxdb port
// DB_NAME - database name
// MEASUREMENT - name of measurement. An example "ElectricMeter"

ini_set('max_execution_time', 10);

function getDataFromMercury() {
    // Parameters for port
    exec('/bin/stty -F /dev/ttyUSB0 9600 ignbrk -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke noflsh -ixon -crtscts');

    // Open port
    $fp = fopen('/dev/ttyUSB0', 'r+');

    if (!$fp) {
      echo 'Could not open com port';
      exit;
    }

    $result = '';
    try {
        // Sent command to device
        stream_set_blocking($fp, 1);

        fwrite($fp, "\x00\x0D\x29\x0F\x63\xb2\xbd"); // string to receiving current amperage,voltage with corrected CRC and device address

        // Read answer from device with 500ms timeout
        stream_set_blocking($fp, 0);
        $timeout = microtime(1) + 0.5;

        while (microtime(1) < $timeout) {
          $c = fgetc($fp);
          if ($c === false) {
            usleep(5);
            continue;
          }

          $result.= $c;
        }
    } finally {
      fclose($fp);
    }

    // split answer data on parts
    // $crc = substr($result, -2); // crc16  of answer
    // $addr = hexdec(bin2hex(substr($result, 1, 3))); // address of power device
    // $answer_cmd = substr($result, 4, -2); // answered command
    $answer = substr($result, 5, -2); // answer string

    // Format and output data
    $voltage = bin2hex(substr($answer, 0, 2)) / 10;
    $amperage = bin2hex(substr($answer, 2, 2)) / 100;
    $energy = bin2hex(substr($answer, 4, 3)) / 1000;

    return array('ac_v'=>$voltage,'ac_i'=>$amperage,'ac_p'=>$energy);
}

function writeMetrics(string $server_host, string $server_port, string $db_name, string $measurement, array $metrics) {
    $url = "http://$server_host:$server_port/write?db=$db_name";
    $data = "$measurement ac_p=${metrics['ac_p']},ac_i=${metrics['ac_i']},ac_v=${metrics['ac_v']}";
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded; charset=UTF-8\r\n" .
            "Authorization: Basic YWRtaW46eVZ3c2JuVXlQd3JaSlpwRzU4bnVxcnFZQUtRZVV0Z25mOFhFNXE4UQ==\r\n",
            'method'  => 'POST',
            'content' => $data
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) {
        writeLog(sprintf("[ERROR] Request to InfluxDB failed. Response headers:\r\n%s",
            print_r($http_response_header, true)));
    } elseif (count($http_response_header) > 1 && strpos($http_response_header[0], 'HTTP/') !== false) {
        $http_code = $http_response_header[0];
        if (strpos($http_code, '200') !== false) {
            writeLog(sprintf("[ERROR] InfluxDB understood the request but couldn’t complete it. Response headers:\r\n%sResponse body:\r\n%s",
                print_r($http_response_header, true), $result));
        } else if (strpos($http_code, '204') !== false) {
            writeLog("[INFO] Metrics are saved succesfully");
        } else {
            writeLog(sprintf("[ERROR] InfluxDB could not understand the request or overloaded. Response headers:%sResponse body:\r\n%s",
                print_r($http_response_header, true), $result));
        }
    } else {
        writeLog(sprintf("[ERROR] Didn't find HTTP code in response. Response headers:\r\n%sResponse body:\r\n%s",
            print_r($http_response_header, true), $result));
    }
}

function writeLog(string $msg) {
    echo sprintf("%s %s" . PHP_EOL, date("Y-m-d H:i:s"), $msg);
}

$metrics = getDataFromMercury();
// $metrics = array('ac_v'=>220,'ac_i'=>1.2,'ac_p'=>3.2); // debug mock
writeMetrics(getenv('SERVER_HOST'), getenv('SERVER_PORT'), getenv('DB_NAME'),
    getenv('MEASUREMENT'), $metrics);
