@props(['preheader' => 'PitchIQ'])
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="dark light">
    <meta name="supported-color-schemes" content="dark light">
    <title>PitchIQ</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#080C0A; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

    {{-- Preheader: the grey preview text shown in the inbox list --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; height:0; width:0;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#080C0A;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    {{-- Header / brand bar (solid green fallback under the gradient for Outlook) --}}
                    <tr>
                        <td align="left" bgcolor="#00b359"
                            style="background-color:#00b359; background:linear-gradient(135deg,#00E676 0%,#00b359 100%); border-radius:16px 16px 0 0; padding:26px 32px;">
                            <span style="font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:24px; font-weight:800; color:#00210b; letter-spacing:-0.5px;">
                                &#9917; Pitch<span style="color:#ffffff;">IQ</span>
                            </span>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="background-color:#0f1411; padding:36px 32px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#cdd3ce;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#0b0f0c; border-radius:0 0 16px 16px; padding:22px 32px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                            <p style="margin:0 0 6px; font-size:12px; line-height:1.5; color:#6b726c;">
                                <strong style="color:#9aa39c;">PitchIQ</strong> — Own Your Squad. Rule the Campus.
                            </p>
                            <p style="margin:0; font-size:11px; line-height:1.5; color:#4b524d;">
                                You're receiving this because you have a PitchIQ account.
                                &copy; {{ date('Y') }} PitchIQ.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
