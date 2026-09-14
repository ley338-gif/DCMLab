import { trans } from '@/lib/trans';

export const categoryLabels: Record<string, string> = {
    netzwerk: trans('Netzwerk'),
    datenmodell: trans('Datenmodell'),
    bildgebung: trans('Bildgebung'),
    integration: trans('Integration'),
    security: trans('Security'),
};

export const difficultyVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    easy: 'outline',
    medium: 'secondary',
    hard: 'default',
    insane: 'destructive',
};
