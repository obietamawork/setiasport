{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($alamat as $a)
    <url>
        <loc>{{ $a['loc'] }}</loc>
@if (! empty($a['lastmod']))
        <lastmod>{{ $a['lastmod']->toAtomString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
