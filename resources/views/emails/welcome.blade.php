@component('mail::message')
# Welcome to PitchIQ, {{ $user->name }}! ⚽

You're all set with **{{ $tokens }} free tokens** to start building squads and making predictions.

Here's how to get started:
- Build a fantasy squad for an upcoming fixture
- Predict match results to earn points
- Climb the campus leaderboard
- Win **airtime** prizes each matchday

@component('mail::button', ['url' => route('dashboard')])
Go to Dashboard
@endcomponent

Invite your friends with your referral link — you both get tokens!

Good luck,<br>
The PitchIQ Team
@endcomponent