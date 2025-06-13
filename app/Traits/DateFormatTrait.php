<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateFormatTrait
{
    public function dateFormat($date)
    {
        if (!$date) return null;
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $bulan = [
            1 => 'januari', 2 => 'februari', 3 => 'maret', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'agustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'desember'
        ];
        $day = $carbon->day;
        $month = $bulan[$carbon->month];
        $year = $carbon->year;
        return "{$day} {$month} {$year}";
    }

    public function formatTanggalLama($date)
    {
        if (!$date) return null;
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $carbon->toISOString();
    }
}
