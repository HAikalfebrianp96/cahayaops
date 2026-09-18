{{-- TTD & STEMPEL --}}
<table style="width: 100%; margin-top: 40px;">
    <tr>
        <td style="width: 60%;"></td>
        <td style="width: 40%; text-align: center;">
            <p style="margin: 0; font-size: 9px; color: #666;">Mengetahui,</p>
            <div style="height: 65px; position: relative;">
                @if ($stempelPath)
                    <img src="{{ $stempelPath }}" style="height: 60px; position: absolute; left: 50%; top: 0; margin-left: -30px; opacity: 0.85;">
                @endif
                @if ($ttdPath)
                    <img src="{{ $ttdPath }}" style="height: 40px; position: absolute; left: 50%; top: 12px; margin-left: -20px;">
                @endif
            </div>
            @if ($setting->nama_penandatangan)
                <p style="margin: 4px 0 0; font-size: 10px; font-weight: bold; border-top: 1px solid #333; padding-top: 4px; display: inline-block; min-width: 140px; color: #0B4F63;">
                    {{ $setting->nama_penandatangan }}
                </p>
                @if ($setting->jabatan_penandatangan)
                    <p style="margin: 0; font-size: 9px; color: #666;">{{ $setting->jabatan_penandatangan }}</p>
                @endif
                <p style="margin: 0; font-size: 8px; color: #999;">{{ $setting->nama }}</p>
            @endif
        </td>
    </tr>
</table>

<p style="margin-top: 30px; font-size: 8px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 6px;">
    Dokumen internal {{ $setting->nama }} — dicetak otomatis dari sistem.
</p>
