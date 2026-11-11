@props(['url'])
{{-- Logo aplikasi di atas kartu, seperti brand pada halaman login Mekaya (images/icon.svg). PNG karena banyak klien email tidak menampilkan SVG. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/mail/icon.png') }}" class="logo" width="48" height="48" alt="{{ trim($slot) }}">
</a>
</td>
</tr>
