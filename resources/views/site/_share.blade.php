{{-- Share row for an article. Expects $post. Facebook, WhatsApp and X open their own share pages; "Copy link" copies the address. --}}
@php
    $shareUrl = route('articles.show', $post->slug);
    $shareText = $post->title;
    $enc = fn ($v) => rawurlencode($v);
@endphp
<div class="share" role="group" aria-label="Share this article">
    <span class="share-label">Share</span>
    <a href="https://wa.me/?text={{ $enc($shareText . ' ' . $shareUrl) }}" target="_blank" rel="noopener noreferrer" class="wa" aria-label="Share on WhatsApp">
        <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7zM9.5 9c.3 2.5 2.5 4.7 5 5l1.2-1.2-1.7-1-.9.6c-.7-.3-1.6-1.2-1.9-1.9l.6-.9-1-1.7z"/></svg>WhatsApp</a>
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $enc($shareUrl) }}" target="_blank" rel="noopener noreferrer" class="fb" aria-label="Share on Facebook">
        <svg class="ic fill" viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.2h-3.1V7.8c0-.9.3-1.5 1.6-1.5h1.6V3.4c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1v2.4H7.6V13h2.7v8z"/></svg>Facebook</a>
    <a href="https://x.com/intent/post?text={{ $enc($shareText) }}&amp;url={{ $enc($shareUrl) }}" target="_blank" rel="noopener noreferrer" class="xx" aria-label="Share on X">
        <svg class="ic fill" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.2 2.25h3.3l-7.2 8.26 8.5 11.24h-6.65l-5.2-6.82-5.97 6.82H1.68l7.73-8.83L1.25 2.25h6.83l4.71 6.23zm-1.16 17.52h1.83L7.08 4.13H5.12z"/></svg>X</a>
    <button type="button" data-copy="{{ $shareUrl }}" aria-label="Copy link">
        <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>Copy link</button>
    <button type="button" data-native-share data-title="{{ $shareText }}" data-url="{{ $shareUrl }}" hidden aria-label="More sharing options">More</button>
</div>
