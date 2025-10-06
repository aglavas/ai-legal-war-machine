<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $law_title ?? 'Zakon' }} — Članak {{ $article_number ?? '' }}</title>
    <style>
        @page { margin: 28mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12pt; color: #111; }
        header { margin-bottom: 16px; }
        h1 { font-size: 16pt; margin: 0 0 6px 0; }
        .meta { font-size: 10pt; color: #555; margin-bottom: 12px; }
        .article { font-size: 12pt; line-height: 1.4; }
        .article h2, .article h3, .article h4 { margin-top: 12px; }
        footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 9pt; color: #888; }
    </style>
</head>
<body>
<header>
    <h1>{{ $law_title ?? 'Zakon' }} — Članak {{ $article_number ?? '' }}</h1>
    <div class="meta">
        @if(!empty($law_eli)) ELI: {{ $law_eli }} · @endif
        @if(!empty($law_pub_date)) Objavljeno: {{ $law_pub_date }} · @endif
        Generirano: {{ $generated_at ?? gmdate('c') }} · Verzija generatora: {{ $generator_version ?? '1.0.0' }}
    </div>
</header>

<section class="article">
{!! $article_html ?? '' !!}
</section>

<footer>
    {{ $law_title ?? '' }} — Članak {{ $article_number ?? '' }}
</footer>
</body>
</html>

