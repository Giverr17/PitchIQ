<x-mail.layout preheader="{{ $friendName }} joined with your link — you earned {{ $reward }} tokens!">

    <h1 style="margin:0 0 16px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:23px; font-weight:800; color:#ffffff;">
        You earned {{ $reward }} tokens! &#129689;
    </h1>

    <p style="margin:0 0 20px; font-size:15px; line-height:1.65; color:#cdd3ce;">
        Nice one, {{ $referrer->name }} — <strong style="color:#ffffff;">{{ $friendName }}</strong>
        signed up with your referral link and just built their first squad.
    </p>

    <x-mail.panel>
        <p style="margin:0; font-size:14px; line-height:1.6; color:#cdd3ce;">
            As a thank you, we've added
            <strong style="color:#00E676; font-size:18px;">{{ $reward }} tokens</strong>
            to your balance.
        </p>
    </x-mail.panel>

    <x-mail.button :url="route('dashboard')">See Your Tokens</x-mail.button>

    <p style="margin:20px 0 0; font-size:14px; line-height:1.65; color:#9aa39c;">
        Keep sharing your link — every friend who joins and plays earns you more.
    </p>

    <p style="margin:24px 0 0; font-size:14px; line-height:1.65; color:#cdd3ce;">
        <strong>The PitchIQ Team</strong>
    </p>

</x-mail.layout>
