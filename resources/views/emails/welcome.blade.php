<x-mail.layout preheader="Welcome to PitchIQ — you've got {{ $tokens }} free tokens to start.">

    <h1 style="margin:0 0 16px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:23px; font-weight:800; color:#ffffff;">
        Welcome, {{ $user->name }}! &#9917;
    </h1>

    <p style="margin:0 0 20px; font-size:15px; line-height:1.65; color:#cdd3ce;">
        Your account is live. You've got <strong style="color:#00E676;">{{ $tokens }} free tokens</strong>
        to start building squads and making match predictions.
    </p>

    <x-mail.panel>
        <p style="margin:0 0 12px; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#00E676;">
            How to get started
        </p>
        <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#cdd3ce;">&#9917;&nbsp; Build a fantasy squad for an upcoming fixture</p>
        <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#cdd3ce;">&#127919;&nbsp; Predict match results to earn points</p>
        <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#cdd3ce;">&#128202;&nbsp; Climb the campus leaderboard</p>
        <p style="margin:0; font-size:14px; line-height:1.6; color:#cdd3ce;">&#128241;&nbsp; Win <strong style="color:#ffffff;">airtime</strong> prizes every matchday</p>
    </x-mail.panel>

    <x-mail.button :url="route('dashboard')">Go to Dashboard</x-mail.button>

    <p style="margin:20px 0 0; font-size:14px; line-height:1.65; color:#9aa39c;">
        Tip: invite friends with your referral link — you <strong style="color:#cdd3ce;">both</strong> get bonus tokens.
    </p>

    <p style="margin:24px 0 0; font-size:14px; line-height:1.65; color:#cdd3ce;">
        Good luck,<br>
        <strong>The PitchIQ Team</strong>
    </p>

</x-mail.layout>
