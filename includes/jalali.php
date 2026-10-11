<?php
/**
 * تبدیل تاریخ میلادی <-> شمسی (جلالی)
 * الگوریتم استاندارد تبدیل تقویم
 */

function gregorian_to_jalali($gy, $gm, $gd, $separator = '/')
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100)
          + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return $jy . $separator . str_pad((string)$jm, 2, '0', STR_PAD_LEFT)
         . $separator . str_pad((string)$jd, 2, '0', STR_PAD_LEFT);
}

function jalali_to_gregorian($jy, $jm, $jd, $separator = '/')
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4)
          + $jd + (($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186));
    $gy = 400 * (int)($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * (int)($days / 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = ((($gy % 4) == 0) && (($gy % 100) != 0)) || (($gy % 400) == 0);
    $sal_a = [0, 31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return $gy . $separator . str_pad((string)$gm, 2, '0', STR_PAD_LEFT)
         . $separator . str_pad((string)$gd, 2, '0', STR_PAD_LEFT);
}

/**
 * تاریخ میلادی 'Y-m-d' یا DateTime → شمسی '1404/07/20'
 */
function jdate($gregorian, $separator = '/')
{
    if (empty($gregorian)) {
        return '';
    }
    if (is_string($gregorian)) {
        $ts = strtotime($gregorian);
        if ($ts === false) {
            return '';
        }
        $gy = (int)date('Y', $ts);
        $gm = (int)date('n', $ts);
        $gd = (int)date('j', $ts);
    } else {
        $gy = (int)$gregorian->format('Y');
        $gm = (int)$gregorian->format('n');
        $gd = (int)$gregorian->format('j');
    }
    return gregorian_to_jalali($gy, $gm, $gd, $separator);
}

/**
 * تاریخ شمسی واردشده کاربر ('1404/07/20' یا '1404-07-20' یا '14040720') → میلادی 'Y-m-d'
 * در صورت نامعتبر بودن null برمی‌گرداند.
 */
function jdate_to_gregorian($jalali)
{
    $jalali = trim((string)$jalali);
    if ($jalali === '') {
        return null;
    }
    // ارقام فارسی/عربی به لاتین
    $jalali = strtr($jalali, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    $jalali = str_replace(['-', '.', ' '], '/', $jalali);
    $parts = explode('/', $jalali);
    if (count($parts) === 3) {
        [$jy, $jm, $jd] = array_map('intval', $parts);
    } elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $jalali, $m)) {
        $jy = (int)$m[1];
        $jm = (int)$m[2];
        $jd = (int)$m[3];
    } else {
        return null;
    }
    if ($jy < 1200 || $jy > 1600 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
        return null;
    }
    [$gy, $gm, $gd] = array_map('intval', explode('/', jalali_to_gregorian($jy, $jm, $jd)));
    if (!checkdate($gm, $gd, $gy)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

/** تاریخ و زمان فعلی به میلادی */
function now_datetime()
{
    return date('Y-m-d H:i:s');
}

function today_gregorian()
{
    return date('Y-m-d');
}
