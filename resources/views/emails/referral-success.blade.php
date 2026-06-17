@component('mail::message')
# You earned 20 tokens! 🪙

Good news, {{ $referrer->name }} — **{{ $friendName }}** signed up with your referral link and just built their first squad.

As a thank you, we've added **{{ $reward }} tokens** to your account.

Keep sharing your link to earn more!

@component('mail::button', ['url' => route('dashboard')])
See Your Tokens
@endcomponent

The PitchIQ Team
@endcomponent