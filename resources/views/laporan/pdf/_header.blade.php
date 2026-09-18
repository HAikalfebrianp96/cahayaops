<div style="width: 100%; text-align: center; margin-bottom: 8px;">
    <img src="{{ $logoPath }}" style="height: 44px;">
</div>

<table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #0B4F63; padding-bottom: 8px;">
    <tr>
        <td style="vertical-align: bottom; padding-bottom: 8px;">
            <p style="margin: 0; font-size: 12px; font-weight: bold; color: #0B4F63;">Program Cahaya Preneur</p>
            <p style="margin: 0; font-size: 9px; color: #666;">YBM PLN UP3 Bengkulu</p>
        </td>
        <td style="vertical-align: bottom; text-align: right; font-size: 9px; color: #666; padding-bottom: 8px;">
            <p style="margin: 0;">Periode: {{ $periodeLabel }}</p>
            <p style="margin: 0;">Dicetak: {{ $tanggalCetak }}</p>
        </td>
    </tr>
</table>

<h2 style="font-size: 14px; color: #0B4F63; margin: 14px 0 4px 0;">{{ $judulLaporan }}</h2>
@if ($filterRingkasan)
    <p style="font-size: 9px; color: #888; margin: 0 0 12px 0;">Filter: {{ $filterRingkasan }}</p>
@else
    <p style="font-size: 9px; color: #888; margin: 0 0 12px 0;">Menampilkan seluruh data (tanpa filter)</p>
@endif
