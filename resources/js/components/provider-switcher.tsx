import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

type ProviderSwitcherProps = {
    availableProviders: Record<string, string>;
    currentProvider: string;
    onChange: (provider: string) => void;
    disabled?: boolean;
};

export default function ProviderSwitcher({ availableProviders, currentProvider, onChange, disabled = false }: ProviderSwitcherProps) {
    const providers = Object.entries(availableProviders).map(([name, label]) => ({
        name,
        label,
    }));

    return (
        <div className="space-y-2">
            <Label className="text-sm font-medium">AI Provider</Label>
            <RadioGroup value={currentProvider} onValueChange={onChange} disabled={disabled} className="flex gap-4">
                {providers.map((provider) => (
                    <div key={provider.name} className="flex items-center gap-2">
                        <RadioGroupItem value={provider.name} id={provider.name} />
                        <Label htmlFor={provider.name} className="cursor-pointer font-normal">
                            {provider.label}
                        </Label>
                    </div>
                ))}
            </RadioGroup>
        </div>
    );
}
