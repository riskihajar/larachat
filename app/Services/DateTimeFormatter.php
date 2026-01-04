<?php

declare(strict_types=1);

namespace App\Services;

class DateTimeFormatter
{
    /**
     * Format tool result into conversational Indonesian response.
     */
    public function formatToolResult(string $toolName, array $result, string $timezone = 'Asia/Makassar'): string
    {
        if (!$result['success']) {
            return $this->formatError($result['error'], $toolName);
        }

        return match ($toolName) {
            'get-current-date-time-tool' => $this->formatCurrentDateTime($result['data'], $timezone),
            'get-timezone-info-tool' => $this->formatTimezoneInfo($result['data'], $timezone),
            'convert-timezone-tool' => $this->formatTimezoneConversion($result['data']),
            'list-timezones-tool' => $this->formatTimezoneList($result['data']),
            default => 'Maaf, saya tidak bisa memproses permintaan itu saat ini.'
        };
    }

    /**
     * Format current datetime into conversational response.
     */
    private function formatCurrentDateTime(array $data, string $timezone): string
    {
        $date = $data['date'] ?? '';
        $time = $data['time'] ?? '';
        $timezoneAbbr = $data['timezone']['abbr'] ?? '';
        $timezoneName = $data['timezone']['name'] ?? $timezone;

        // Parse date for Indonesian formatting
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        $indoDate = '';
        if ($dateObj) {
            $indoDate = $this->formatIndonesianDate($dateObj);
        }

        // Parse time
        $timeObj = \DateTime::createFromFormat('H:i:s', $time);
        $indoTime = '';
        if ($timeObj) {
            $indoTime = $this->formatIndonesianTime($timeObj);
        }

        $responses = [
            "Sekarang {$indoDate}, {$indoTime} {$timezoneAbbr}",
            "Hari ini tanggal {$indoDate}, waktu menunjukkan {$indoTime} {$timezoneAbbr}",
            "Saat ini {$indoDate} pukul {$indoTime} {$timezoneAbbr}",
            "Waktu sekarang {$indoTime} {$timezoneAbbr}, tanggal {$indoDate}"
        ];

        return $responses[array_rand($responses)];
    }

    /**
     * Format timezone info into conversational response.
     */
    private function formatTimezoneInfo(array $data, string $timezone): string
    {
        $requestedTimezone = $data['requested_timezone'] ?? $timezone;
        $currentTime = $data['current_time']['datetime'] ?? '';
        $offset = $data['timezone_info']['offset'] ?? 0;
        $abbr = $data['timezone_info']['abbr'] ?? '';
        $dst = $data['timezone_info']['dst'] ?? false;

        $offsetHours = abs($offset) / 3600;
        $offsetSign = $offset >= 0 ? '+' : '-';
        $offsetText = "UTC{$offsetSign}{$offsetHours}";

        $dstText = $dst ? " (dalam daylight saving time)" : "";

        return "Zona waktu {$requestedTimezone} adalah {$offsetText} {$abbr}{$dstText}. Waktu saat ini: {$currentTime}.";
    }

    /**
     * Format timezone conversion into conversational response.
     */
    private function formatTimezoneConversion(array $data): string
    {
        $original = $data['original'] ?? [];
        $converted = $data['converted'] ?? [];
        $input = $data['input'] ?? [];

        $fromTz = $original['timezone'] ?? $input['from_timezone'] ?? '';
        $toTz = $converted['timezone'] ?? $input['to_timezone'] ?? '';
        $fromTime = $original['datetime'] ?? '';
        $toTime = $converted['datetime'] ?? '';
        $diff = $data['conversion_info']['timezone_difference'] ?? 0;

        if ($diff > 0) {
            $diffText = "lebih cepat {$diff} jam";
        } elseif ($diff < 0) {
            $diffText = "lebih lambat " . abs($diff) . " jam";
        } else {
            $diffText = "sama";
        }

        return "Waktu {$fromTime} di {$fromTz} sama dengan {$toTime} di {$toTz} (zona waktu {$toTz} {$diffText}).";
    }

    /**
     * Format timezone list into conversational response.
     */
    private function formatTimezoneList(array $data): string
    {
        $totalTimezones = $data['total_timezones'] ?? 0;
        $timezones = $data['timezones'] ?? [];

        $popularTimezones = [
            'Asia/Makassar' => 'Makassar (WITA)',
            'Asia/Jakarta' => 'Jakarta (WIB)',
            'UTC' => 'UTC',
            'America/New_York' => 'New York (EST)',
            'Europe/London' => 'London (GMT)',
            'Asia/Tokyo' => 'Tokyo (JST)',
            'Australia/Sydney' => 'Sydney (AEDT)'
        ];

        $availableText = "Ada {$totalTimezones} zona waktu yang tersedia. Beberapa yang populer: ";
        $examples = [];

        foreach ($popularTimezones as $tz => $name) {
            if (in_array($tz, $timezones)) {
                $examples[] = $name;
            }
        }

        return $availableText . implode(', ', $examples) . ".";
    }

    /**
     * Format error into conversational response.
     */
    private function formatError(string $error, string $toolName): string
    {
        $errorMessages = [
            'get-current-date-time-tool' => 'Maaf, saya tidak bisa mendapatkan waktu saat ini. Coba lagi sebentar ya.',
            'get-timezone-info-tool' => 'Maaf, saya tidak bisa mendapatkan informasi timezone. Mungkin zona waktu yang diminta tidak valid.',
            'convert-timezone-tool' => 'Maaf, saya tidak bisa mengkonversi waktu. Pastikan timezone yang Anda masukkan benar.',
            'list-timezones-tool' => 'Maaf, saya tidak bisa mengambil daftar timezone saat ini.',
            'default' => 'Maaf, terjadi kesalahan saat memproses permintaan Anda.'
        ];

        return $errorMessages[$toolName] ?? $errorMessages['default'];
    }

    /**
     * Format date into Indonesian format.
     */
    private function formatIndonesianDate(\DateTime $date): string
    {
        $day = $date->format('j');
        $month = (int) $date->format('n');
        $year = $date->format('Y');

        $indoMonths = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return "{$day} {$indoMonths[$month]} {$year}";
    }

    /**
     * Format time into Indonesian format.
     */
    private function formatIndonesianTime(\DateTime $time): string
    {
        $hour = (int) $time->format('H');
        $minute = (int) $time->format('i');

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * Check if a message is likely a datetime query.
     */
    public function isDateTimeQuery(string $message): bool
    {
        $datetimeKeywords = [
            'tanggal', 'waktu', 'jam', 'sekarang', 'hari ini', 'besok', 'kemarin',
            'bulan', 'tahun', 'timezone', 'zona waktu', 'konversi waktu',
            'jam berapa', 'tanggal berapa', 'waktu sekarang', 'sekarang jam',
            'date', 'time', 'now', 'timezone', 'convert time'
        ];

        $messageLower = strtolower($message);
        
        foreach ($datetimeKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract timezone from message if mentioned.
     */
    public function extractTimezoneFromMessage(string $message): ?string
    {
        // Common timezone patterns in Indonesian/English
        $timezonePatterns = [
            'makassar' => 'Asia/Makassar',
            'jakarta' => 'Asia/Jakarta',
            'utc' => 'UTC',
            'new york' => 'America/New_York',
            'london' => 'Europe/London',
            'tokyo' => 'Asia/Tokyo',
            'sydney' => 'Australia/Sydney',
            'los angeles' => 'America/Los_Angeles',
            'singapore' => 'Asia/Singapore',
            'hong kong' => 'Asia/Hong_Kong'
        ];

        $messageLower = strtolower($message);
        
        foreach ($timezonePatterns as $pattern => $timezone) {
            if (str_contains($messageLower, $pattern)) {
                return $timezone;
            }
        }

        return null;
    }
}