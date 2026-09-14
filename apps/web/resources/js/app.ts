import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import GlobalLayout from '@/layouts/GlobalLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // LessonLayout verwaltet Header/3-Spalten-Body selbst (nutzt
            // intern ebenfalls GlobalHeader, siehe layouts/lesson/LessonLayout.vue).
            case name === 'Lessons/Show':
            // Oeffentliche, login-freie Seiten mit eigenem, vollstaendigem
            // Seiten-Shell (Header + main) -- ohne diesen Fall rutschen sie in
            // den default-Fall (AppLayout) und werden faelschlich in die
            // eingeloggte App-Shell samt Sidebar genestet, obwohl sie explizit
            // ohne Login und ohne die eingeloggte Navigation gedacht sind.
            case name === 'Profiles/Show':
            case name === 'Leaderboard':
                return null;
            case name === 'Tracks/Index':
            case name === 'Tracks/Show':
            case name === 'Nodes/Show':
            case name === 'Nodes/Index':
            case name === 'Glossary/Index':
            case name === 'Dashboard':
            case name === 'Review/Index':
                return GlobalLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
