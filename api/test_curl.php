<?php
header('Content-Type: text/plain; charset=UTF-8');
echo "curl: " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";

$ch = curl_init('https://www.xplace.com/il/job/213428');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0',
]);
$html = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo "error: $err\n";
echo "html_len: " . strlen($html) . "\n";

if ($html && preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) {
    echo "desc: " . mb_substr(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), 0, 200) . "\n";
} else {
    echo "no og:description found\n";
}
