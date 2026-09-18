<div style="width: 100%; text-align: center; margin-bottom: 8px;">
    <img src="{{ $logoPath }}" style="height: 44px;">
</div>

<table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #0B4F63; padding-bottom: 8px;">
    <tr>
        <td style="vertical-align: bottom; padding-bottom: 8px;">
            <p style="margin: 0; font-size: 13px; font-weight: bold; color: #0B4F63;">{{ $setting->nama }}</p>
            <p style="margin: 0; font-size: 9px; color: #666;">{{ $setting->subtitle }}</p>
            <p style="margin: 0; font-size: 8px; color: #F5C518;">{{ $setting->tagline }}</p>
        </td>
        <td style="vertical-align: bottom; text-align: right; font-size: 9px; color: #666; padding-bottom: 8px;">
            <p style="margin: 0;">Periode: {{ $periodeLabel }}</p>
            <p style="margin: 0;">Dicetak: {{ now()->translatedFormat('d F Y H:i') }} WIB</p>
        </td>
    </tr>
</table>
