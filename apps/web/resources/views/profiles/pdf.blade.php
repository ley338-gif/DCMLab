<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $profile['name'] }}</title>
    <style>
        body { font-family: sans-serif; color: #111; }
        h1 { margin-bottom: 0; }
        .rank { color: #555; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>{{ $profile['name'] }}</h1>
    <p class="rank">{{ __('rank.'.$profile['rank']) }} &middot; {{ $profile['points'] }} {{ __('Points') }}</p>
    @if ($profile['member_since'])
        <p>{{ __('Member since :date', ['date' => $profile['member_since']]) }}</p>
    @endif

    <h2>{{ __('Skill radar') }}</h2>
    <table>
        <tbody>
            @foreach ($profile['skill_vector'] as $skill => $points)
                <tr>
                    <td>{{ __('skill.'.$skill) }}</td>
                    <td>{{ $points }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('First Blood') }}</h2>
    @if (count($profile['first_bloods']) === 0)
        <p>{{ __('No first bloods yet.') }}</p>
    @else
        <table>
            <tbody>
                @foreach ($profile['first_bloods'] as $entry)
                    <tr>
                        <td>{{ $entry['node_title'] }}</td>
                        <td>{{ $entry['awarded_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
