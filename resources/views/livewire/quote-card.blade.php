<div>
  <div>
    @foreach($themes as $theme)
      <button type="button" wire:click="selectTheme('{{ $theme }}')">{{ __('reader.quote_theme_'.$theme) }}</button>
    @endforeach
  </div>
  <div id="quote-card" data-theme="{{ $theme }}" style="border:1px solid #ccc; padding:16px; margin:12px 0;">
    <p style="font-size:1.2em;">“{{ $quote }}”</p>
    <p>{{ $book->title }} — {{ $book->author->name ?? '' }}</p>
    <p>LearnVow</p>
  </div>
  <button type="button" class="btn" onclick="navigator.share ? navigator.share({text: document.getElementById('quote-card').innerText}) : alert('{{ __('reader.quote_share_unsupported') }}')">{{ __('reader.quote_share') }}</button>
  <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('quote-card').innerText)">{{ __('reader.quote_copy') }}</button>
  <button type="button" class="btn" onclick="downloadQuoteCard()">{{ __('reader.quote_download_png') }}</button>
  <script>
    function downloadQuoteCard() {
      var el = document.getElementById('quote-card');
      var canvas = document.createElement('canvas');
      canvas.width = 800; canvas.height = 400;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#111111'; ctx.font = '28px serif';
      var text = el ? el.innerText : '';
      var lines = text.split('\n');
      lines.forEach(function (line, i) { ctx.fillText(line.slice(0, 60), 40, 60 + i * 40); });
      var a = document.createElement('a');
      a.download = 'learnvow-quote.png';
      a.href = canvas.toDataURL('image/png');
      a.click();
    }
  </script>
</div>
