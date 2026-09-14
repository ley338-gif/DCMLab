export interface Achievement {
    slug: string;
    name: string;
    description: string;
    image: string;
    category: string;
    rarity: string | null;
    unlocked: boolean;
    unlocked_at: string | null;
}
