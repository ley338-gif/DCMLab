<x-mail::message>
# Fällige Wissenskarten

Hallo {{ $user->name }},

du hast {{ $dueCount }} {{ $dueCount === 1 ? 'fällige Wissenskarte' : 'fällige Wissenskarten' }} zur Wiederholung.

<x-mail::button :url="$reviewUrl">
Jetzt wiederholen
</x-mail::button>

Diese Erinnerung kannst du jederzeit in deinen Kontoeinstellungen abschalten.

<x-mail::button :url="$settingsUrl" color="primary">
Zu den Kontoeinstellungen
</x-mail::button>

Viele Grüße,<br>
{{ config('app.name') }}
</x-mail::message>
