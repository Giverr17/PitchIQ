@props(['url' => '#'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;">
    <tr>
        <td align="center" bgcolor="#00E676" style="border-radius:10px; background-color:#00E676;">
            <a href="{{ $url }}" target="_blank"
               style="display:inline-block; padding:14px 32px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:700; line-height:1; color:#00210b; text-decoration:none; border-radius:10px;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
