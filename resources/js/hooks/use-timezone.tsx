import { useEffect, useState } from 'react';

export interface TimezoneInfo {
    timezone: string;
    abbreviation: string;
    offset: string;
    isDST: boolean;
}

export function useTimezone() {
    const [timezoneInfo, setTimezoneInfo] = useState<TimezoneInfo>({
        timezone: 'Asia/Makassar',
        abbreviation: 'WITA',
        offset: '+08:00',
        isDST: false,
    });
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const detectTimezone = () => {
            try {
                // Get browser timezone
                const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

                // Default to Makassar for Indonesian users if timezone detection fails
                const userTimezone = detectedTimezone || 'Asia/Makassar';

                // Get timezone abbreviation and offset
                const now = new Date();
                const timezone = new Intl.DateTimeFormat('en-US', {
                    timeZone: userTimezone,
                    timeZoneName: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                }).formatToParts(now);

                const abbreviation = timezone.find((part) => part.type === 'timeZoneName')?.value || 'WITA';

                // Get UTC offset
                const offsetMinutes = -now.getTimezoneOffset();
                const offsetHours = Math.floor(offsetMinutes / 60);
                const offsetMins = offsetMinutes % 60;
                const offsetString = `${offsetHours >= 0 ? '+' : ''}${offsetHours.toString().padStart(2, '0')}:${offsetMins.toString().padStart(2, '0')}`;

                // Check if DST is active (simplified check)
                const jan = new Date(now.getFullYear(), 0, 1);
                const jul = new Date(now.getFullYear(), 6, 1);
                const isDST = now.getTimezoneOffset() < Math.max(jan.getTimezoneOffset(), jul.getTimezoneOffset());

                const newTimezoneInfo: TimezoneInfo = {
                    timezone: userTimezone,
                    abbreviation,
                    offset: offsetString,
                    isDST,
                };

                setTimezoneInfo(newTimezoneInfo);

                // Store in localStorage for persistence
                localStorage.setItem('user_timezone', JSON.stringify(newTimezoneInfo));
            } catch (error) {
                console.warn('Failed to detect timezone:', error);
                // Keep default Makassar timezone
            } finally {
                setIsLoading(false);
            }
        };

        // Try to get stored timezone first
        const stored = localStorage.getItem('user_timezone');
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                setTimezoneInfo(parsed);
                setIsLoading(false);
                return;
            } catch (error) {
                console.warn('Failed to parse stored timezone:', error);
            }
        }

        detectTimezone();
    }, []);

    const updateTimezone = (timezone: string) => {
        const now = new Date();
        const timezoneObj = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            timeZoneName: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).formatToParts(now);

        const abbreviation = timezoneObj.find((part) => part.type === 'timeZoneName')?.value || 'WITA';

        const newTimezoneInfo: TimezoneInfo = {
            timezone,
            abbreviation,
            offset: timezoneInfo.offset,
            isDST: timezoneInfo.isDST,
        };

        setTimezoneInfo(newTimezoneInfo);
        localStorage.setItem('user_timezone', JSON.stringify(newTimezoneInfo));
    };

    return {
        timezone: timezoneInfo.timezone,
        abbreviation: timezoneInfo.abbreviation,
        offset: timezoneInfo.offset,
        isDST: timezoneInfo.isDST,
        isLoading,
        updateTimezone,
        timezoneInfo,
    };
}

// Helper function to get timezone from browser
export function getBrowserTimezone(): string {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Makassar';
    } catch (error) {
        return 'Asia/Makassar';
    }
}

// Helper function to format timezone for API calls
export function formatTimezoneForAPI(timezoneInfo: TimezoneInfo) {
    return {
        timezone: timezoneInfo.timezone,
        abbreviation: timezoneInfo.abbreviation,
        offset: timezoneInfo.offset,
        is_dst: timezoneInfo.isDST,
    };
}
