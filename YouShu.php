<?php
/**
 * TCP 协议类
 * User: lishuai
 * Date: 2020/9/27
 * Time: 10:03 PM
 */

namespace App\Protocol;

use Illuminate\Support\Facades\Log;

class YouShu{

    static public function decode($string)
    {
        try{
            $hex = str_split($string,2);

            $req = [
                "start" => $hex[0],
                "control" => $hex[1],
                "data_len" => $hex[2],
                "header_check" => $hex[3],
                "device_number" => $hex[4],
                "func" => $hex[5],
                "channel_number" => $hex[6],
            ];
            $pos = 7;

            while ($pos < count($hex) - 2){
                $channel = (intval($hex[$pos], 16) ^ 0x80) + 1;
                $req['ch' . $channel] = self::hex2float([
                    intval($hex[$pos + 1], 16),
                    intval($hex[$pos + 2], 16),
                    intval($hex[$pos + 3], 16),
                    intval($hex[$pos + 4], 16)
                ]);

                $pos += 5;
            }
            $req["crc"] = $hex[$pos] . $hex[$pos + 1];

            return $req;
        }catch (\Exception $exception){
            Log::channel("daily")->error("YantuDTError:" . $string);
        }
    }

    static public function encode($cmd)
    {
        if ($cmd == []) return false;

        $device_number = $cmd['device_number'];
        $func = $cmd['func'];
        $extra = $cmd['extra'];

        $len = strlen($extra);
        if ($len % 2 != 0) return false;

        $resp = [
            "start"=> 0xF7,
            "control" => 0x60,
            "data_len" => 0,
            "header_check" => 0,
            "address" => $device_number,
            "func" => $func,
            "channel_number" => 0,
        ];

        $resp["channel_number"] = $len / 2;
        $resp["data_len"] = $resp["channel_number"] + 2;
        $resp["header_check"] = ($resp["start"] + $resp["control"] + $resp["data_len"]) % 256;

        $hex = "";
        foreach ($resp as $item) {
            $hex.= str_pad(strtoupper(dechex(intval($item,16))),2,'0',STR_PAD_LEFT);
        }
        $hex .= $extra;
        $crc = self::crc16($hex);

        return $hex . $crc;
    }

    static public function crc16($string)
    {
        $length = strlen($string);
        if ($length % 2 != 0) return "";
        $hex = str_split($string,2);
        $crc = 0;
        for ($i = 0; $i < count($hex); $i++) {
            $b = intval($hex[$i], 16);
            for ($j = 0; $j < 8; $j++) {
                $bit = (($b >> (7 - $j) & 1) == 1);
                $c15 = (($crc >> 15 & 1) == 1);
                $crc = $crc << 1;
                if ($c15 ^ $bit) $crc ^= 0x1021;
            }
        }

        $crc = $crc & 0xffff;
        return str_pad(strtoupper(dechex($crc)),4,'0',STR_PAD_LEFT);
    }

    static public function hex2float($arr)
    {
        if (count($arr) != 4) return false;

        if ($arr == [0, 0, 0, 0]) return null;

        $v = (0xff & $arr[0]) | (0xff00 & $arr[1] << 8) |
            (0xff0000 & $arr[2] << 16)| (0xff000000 & $arr[3] << 24);

        $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
        $exp = ($v >> 23 & 0xFF) - 127;
        return $x * pow(2, $exp - 23);
    }
}

//YouShu::decode("f7605cb301c012804ad11e458126fe20458200000000830000000084000000008595e32445860000000087dd74234588a0b4dc4189e085dc418a000000008b000000008c000000008db065dc418e000000008f600eda4190f8cd9040910bdc3f411ab2");
//echo YouShu::crc16("F760045B0240020001");
//echo YouShu::encode([
//    "device_number" => 1,
//    "func" => '40',
//    "extra" => '0001',
//]);
//echo YouShu::hex2float([0xD5, 0x8D, 0xF1, 0x41]);
