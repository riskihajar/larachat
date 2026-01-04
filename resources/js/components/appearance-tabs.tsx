import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Appearance, useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Monitor, Moon, Sun } from 'lucide-react';

interface AppearanceToggleTabProps {
    className?: string;
}

export default function AppearanceToggleTab({ className }: AppearanceToggleTabProps) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: typeof Sun; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <Tabs
            value={appearance}
            onValueChange={(value) => updateAppearance(value as Appearance)}
            orientation="vertical"
            className={cn('w-full', className)}
        >
            <TabsList className="flex h-auto w-full flex-col items-stretch gap-1 bg-transparent p-0">
                {tabs.map(({ value, icon: Icon, label }) => (
                    <TabsTrigger key={value} value={value} className="data-[state=active]:bg-muted justify-start gap-2 px-3 py-2">
                        <Icon className="size-4" />
                        <span>{label}</span>
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
