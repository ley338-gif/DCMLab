import { trans } from '@/lib/trans';

export const difficultyLabels: Record<string, string> = {
    easy: trans('Leicht'),
    medium: trans('Mittel'),
    hard: trans('Schwer'),
    insane: trans('Extrem'),
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
