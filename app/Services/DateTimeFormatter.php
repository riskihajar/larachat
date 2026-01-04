<?php

declare(strict_types=1);

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DateTimeFormatter
{
    private array $datetimePatterns = [
        "What time is it now",
        "What's the current time",
        "What date is today", 
        "What day is today",
        "Current date and time",
        "Timezone information",
        "Convert time between timezones",
        "Jam berapa sekarang",
        "Tanggal berapa hari ini",
        "Hari apa ini sekarang",
        "Waktu saat ini",
        "Konversi zona waktu",
        "Informasi timezone",
        "Jam berapa di Jakarta",
        "Sekarang jam berapa",
        "Tanggal hari ini",
        "Waktu sekarang"
    ];

    private array $patternEmbeddings = [];
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
     * Check if a message is likely a datetime query using LLM reasoning.
     */
    public function isDateTimeQuery(string $message): bool
    {
        // Fast keyword check first (performance optimization)
        if ($this->keywordMatch($message)) {
            return true;
        }

        // Use LLM reasoning for better accuracy
        return $this->reasoningMatch($message);
    }

    /**
     * Use LLM reasoning to determine if message is datetime-related.
     */
    private function reasoningMatch(string $message): bool
    {
        // Skip if no API key (fallback to enhanced patterns)
        if (!$this->isOpenAIAvailable()) {
            return $this->enhancedPatternMatch($message);
        }

        try {
            // Use LLM to classify the intent
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini', // Fast and cheap for classification
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a datetime query classifier. Classify if the user message is asking about time, date, timezone, or datetime-related information. Respond with only "YES" or "NO". Examples: "What time is it?" = YES, "Hello" = NO, "Tell me the date" = YES, "How is the weather" = NO'
                    ],
                    [
                        'role' => 'user',
                        'content' => $message
                    ]
                ],
                'max_tokens' => 5,
                'temperature' => 0
            ]);

            $classification = trim(strtolower($response->choices[0]->message->content));
            
            Log::debug('LLM datetime classification', [
                'message' => $message,
                'classification' => $classification
            ]);

            return $classification === 'yes';
            
        } catch (\Exception $e) {
            Log::warning('LLM reasoning failed, falling back to patterns', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            
            // Fallback to enhanced patterns
            return $this->enhancedPatternMatch($message);
        }
    }

    /**
     * Check if OpenAI API is available and configured.
     */
    private function isOpenAIAvailable(): bool
    {
        return !app()->environment('testing') && 
               config('openai.api_key') && 
               !empty(config('openai.api_key'));
    }

    /**
     * Traditional keyword matching (fallback).
     */
    private function keywordMatch(string $message): bool
    {
        $datetimeKeywords = [
            'tanggal', 'waktu', 'jam', 'sekarang', 'hari', 'hari ini', 'besok', 'kemarin',
            'bulan', 'tahun', 'timezone', 'zona waktu', 'konversi waktu',
            'jam berapa', 'tanggal berapa', 'hari apa', 'waktu sekarang', 'sekarang jam',
            'date', 'time', 'now', 'timezone', 'convert time', 'what day', 'day today'
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
     * Enhanced pattern matching without embeddings (fallback).
     */
    private function enhancedPatternMatch(string $message): bool
    {
        $enhancedPatterns = [
            // Time patterns
            '/\b(time|jam|waktu)\b.*\b(now|sekarang|current|saat ini)\b/i',
            '/\b(now|saat ini|sekarang)\b.*\b(time|jam|waktu)\b/i',
            
            // Date patterns  
            '/\b(date|tanggal)\b.*\b(today|hari ini|sekarang)\b/i',
            '/\b(today|hari ini)\b.*\b(date|tanggal)\b/i',
            
            // Day patterns
            '/\b(day|hari)\b.*\b(today|sekarang|ini)\b/i',
            '/\b(what.*day|hari.*apa)\b/i',
            
            // Timezone patterns
            '/\b(timezone|zona waktu)\b.*\b(info|information|informasi)\b/i',
            '/\b(info|informasi)\b.*\b(timezone|zona waktu)\b/i',
            '/\b(timezone|zona waktu)\b.*\b(what|apa|which)\b/i',
            
            // Conversion patterns
            '/\b(convert|konversi)\b.*\b(time|timezone|waktu)\b/i',
            '/\b(time|timezone|waktu)\b.*\b(convert|konversi)\b/i',
            
            // Location + time patterns
            '/\b(what.*time|time.*what)\b.*\b(in|di)\b.*\b(jakarta|makassar|bandung|surabaya|medan)\b/i',
            '/\b(jam.*berapa|what.*time)\b.*\b(di|in)\b.*\b(jakarta|makassar|bandung|surabaya|medan)\b/i',
        ];

        foreach ($enhancedPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Semantic similarity matching using OpenAI embeddings.
     */
    private function semanticMatch(string $message): bool
    {
        try {
            // Get embedding for the user message
            $queryEmbedding = $this->getEmbedding($message);
            
            // Initialize patterns if not already done
            if (empty($this->patternEmbeddings)) {
                $this->initializePatternEmbeddings();
            }

            // If patterns failed to initialize, fall back
            if (empty($this->patternEmbeddings)) {
                return $this->enhancedPatternMatch($message);
            }

            // Find most similar pattern
            $maxSimilarity = 0;
            foreach ($this->patternEmbeddings as $pattern => $embedding) {
                $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
                $maxSimilarity = max($maxSimilarity, $similarity);
                
                // Early exit if we find a very high similarity
                if ($similarity > 0.85) {
                    return true;
                }
            }

            // Return true if similarity is above threshold
            return $maxSimilarity > 0.75;
            
        } catch (\Exception $e) {
            Log::warning('Semantic matching failed, falling back to enhanced patterns', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            
            // Fallback to enhanced pattern matching
            return $this->enhancedPatternMatch($message);
        }
    }

    /**
     * Get embedding for a text using OpenAI.
     */
    private function getEmbedding(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text
        ]);

        return $response->embeddings[0]->embedding;
    }

    /**
     * Initialize embeddings for predefined datetime patterns.
     */
    private function initializePatternEmbeddings(): void
    {
        // Check cache first
        $cachedEmbeddings = Cache::get('datetime_pattern_embeddings');
        if ($cachedEmbeddings) {
            $this->patternEmbeddings = $cachedEmbeddings;
            return;
        }

        try {
            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $this->datetimePatterns
            ]);

            foreach ($response->embeddings as $index => $embedding) {
                $this->patternEmbeddings[$this->datetimePatterns[$index]] = $embedding->embedding;
            }

            // Cache for 1 hour
            Cache::put('datetime_pattern_embeddings', $this->patternEmbeddings, 3600);
            
        } catch (\Exception $e) {
            Log::error('Failed to initialize pattern embeddings', [
                'error' => $e->getMessage()
            ]);
            // If embeddings fail, patterns remain empty and semantic matching will be skipped
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        $magnitude = sqrt($normA) * sqrt($normB);
        
        if ($magnitude == 0) {
            return 0;
        }

        return $dotProduct / $magnitude;
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