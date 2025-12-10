import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';

type ModelGroup = {
    provider: string;
    providerLabel: string;
    models: Record<string, string>;
};

type ModelSelectorProps = {
    availableModels: Record<string, { label: string; models: Record<string, string> }>;
    currentModel: string; // format: "provider:model"
    onChange: (model: string) => void;
    disabled?: boolean;
};

export default function ModelSelector({ availableModels, currentModel, onChange, disabled = false }: ModelSelectorProps) {
    // Transform availableModels into grouped structure
    const modelGroups: ModelGroup[] = Object.entries(availableModels).map(([provider, { label, models }]) => ({
        provider,
        providerLabel: label,
        models,
    }));

    // Get display label for current selection
    const getCurrentLabel = () => {
        const [provider, modelKey] = currentModel.split(':');
        const providerData = availableModels[provider];

        if (!providerData) return 'Select a model';

        const modelLabel = providerData.models[modelKey];
        return modelLabel ? `${providerData.label}: ${modelLabel}` : 'Select a model';
    };

    return (
        <div className="space-y-2">
            <Label className="text-sm font-medium">AI Model</Label>
            <Select value={currentModel} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select a model">{getCurrentLabel()}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {modelGroups.map((group) => (
                        <SelectGroup key={group.provider}>
                            <SelectLabel>{group.providerLabel}</SelectLabel>
                            {Object.entries(group.models).map(([modelKey, modelLabel]) => (
                                <SelectItem key={`${group.provider}:${modelKey}`} value={`${group.provider}:${modelKey}`}>
                                    {modelLabel}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
