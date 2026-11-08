<?php

use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    /** $25.000.000 */
    function money(float|string|null $value): string
    {
        $v = (float) $value;

        return ($v < 0 ? '-' : '') . '$' . number_format(abs($v), 0, ',', '.');
    }
}

if (! function_exists('money_m')) {
    /** $1.250M (millones, sin decimales) */
    function money_m(float|string|null $value): string
    {
        return '$' . number_format(round((float) $value / 1_000_000), 0, ',', '.') . 'M';
    }
}

if (! function_exists('month_short')) {
    /** Ene 26 */
    function month_short(Carbon $date): string
    {
        $m = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        return $m[$date->month - 1] . ' ' . $date->format('y');
    }
}

if (! function_exists('date_es')) {
    /** 15 ene 2026 */
    function date_es(?Carbon $date): string
    {
        if (! $date) {
            return '—';
        }
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

        return $date->day . ' ' . $m[$date->month - 1] . ' ' . $date->year;
    }
}

if (! function_exists('month_long')) {
    /** septiembre 2026 */
    function month_long(Carbon $date): string
    {
        $m = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $m[$date->month - 1] . ' ' . $date->year;
    }
}

if (! function_exists('days_ago')) {
    function days_ago(Carbon $date): string
    {
        $d = (int) $date->copy()->startOfDay()->diffInDays(today());

        return match (true) {
            $d === 0 => 'Hoy',
            $d === 1 => 'Hace 1 día',
            default => "Hace {$d} días",
        };
    }
}

if (! function_exists('csv_download')) {
    /** Descarga CSV con separador «;» y BOM, para que Excel lo abra con tildes. */
    function csv_download(string $name, array $header, iterable $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, "{$name}-" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
