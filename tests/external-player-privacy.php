<?php
require __DIR__.'/bootstrap.php';
use Systopic\Cms\Media\EmbedConsent;
$youtube=EmbedConsent::html((object)['url'=>'https://youtu.be/dQw4w9WgXcQ'], ['scope'=>'view']);
$vimeo=EmbedConsent::html((object)['url'=>'https://player.vimeo.com/video/123456789?h=secret123'], ['scope'=>'view']);
check('YouTube is initially a button, not a remote iframe',!str_contains($youtube,'<iframe') && str_contains($youtube,'data-embed-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0"'));
check('Vimeo private-video hash and privacy flag preserved',str_contains($vimeo,'?dnt=1&amp;h=secret123'));
check('unsupported URL cannot become a player',EmbedConsent::html((object)['url'=>'https://example.org/video'], ['scope'=>'view'])==='');
file_put_contents('/private/tmp/fluxo-external-player.html',$youtube.$vimeo);
