import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const Placeholder = {
    title: <div className="bg-secondary h-6 w-full max-w-20 rounded-md" />,
    content: <div className="bg-secondary h-20 w-full rounded-md" />,
};

export const Card_24 = () => {
    return (
        <div className="border-border/70 rounded-xl border p-1">
            <Card className="bg-muted/20 rounded-lg">
                <CardHeader>
                    <CardTitle>{Placeholder.title}</CardTitle>
                </CardHeader>
                <CardContent>{Placeholder.content}</CardContent>
            </Card>
        </div>
    );
};
