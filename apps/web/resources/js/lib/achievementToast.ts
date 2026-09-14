import { toast } from 'vue-sonner';
import AchievementUnlockToast from '@/components/achievements/AchievementUnlockToast.vue';
import type { Achievement } from '@/types/achievement';

/**
 * Zeigt eine dezente Unlock-Notification je neu freigeschaltetem Achievement
 * (Achievement-System, Abschnitt 12) -- ueber das im Projekt bereits
 * vorhandene vue-sonner statt einer eigenen Notification-Loesung.
 */
export function showAchievementUnlockToasts(achievements: Achievement[]): void {
    for (const achievement of achievements) {
        toast.custom(AchievementUnlockToast, {
            componentProps: { achievement },
            duration: 4000,
        });
    }
}
