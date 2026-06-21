<x-mail.layout preheader="You won &#8358;{{ $amount }} airtime on PitchIQ!">

    <h1 style="margin:0 0 16px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:23px; font-weight:800; color:#ffffff;">
        Congratulations, {{ $user->name }}! &#127942;
    </h1>

    <p style="margin:0 0 20px; font-size:15px; line-height:1.65; color:#cdd3ce;">
        You finished <strong style="color:#00E676;">#{{ $position }}</strong> in
        <strong style="color:#ffffff;">{{ $tournamentName }}</strong> — Matchday {{ $matchday }}.
    </p>

    <x-mail.panel>
        <p style="margin:0 0 4px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#9aa39c;">
            Airtime sent
        </p>
        <p style="margin:0 0 6px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:28px; font-weight:800; color:#00E676;">
            &#8358;{{ $amount }}
        </p>
        <p style="margin:0; font-size:13px; line-height:1.5; color:#9aa39c;">
            to your number ending <strong style="color:#cdd3ce;">{{ substr($phone, -4) }}</strong>
        </p>
    </x-mail.panel>

    <p style="margin:0 0 4px; font-size:14px; line-height:1.6; color:#9aa39c;">
        Airtime should arrive within a few minutes. If you don't receive it, just reply to this email.
    </p>

    <x-mail.button :url="route('leaderboard')">View Leaderboard</x-mail.button>

    <p style="margin:24px 0 0; font-size:14px; line-height:1.65; color:#cdd3ce;">
        Keep playing to win more,<br>
        <strong>The PitchIQ Team</strong>
    </p>

</x-mail.layout>
