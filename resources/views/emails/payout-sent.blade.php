@component('mail::message')
# Congratulations, {{ $user->name }}! 🏆

You finished **#{{ $position }}** in {{ $tournamentName }} — Matchday {{ $matchday }}.

We've sent **₦{{ $amount }} airtime** to your number ending **{{ substr($phone, -4) }}**.

@component('mail::panel')
Airtime should arrive within a few minutes. If you don't receive it, reply to this email.
@endcomponent

Keep playing to win more!

@component('mail::button', ['url' => route('leaderboard')])
View Leaderboard
@endcomponent

The PitchIQ Team
@endcomponent