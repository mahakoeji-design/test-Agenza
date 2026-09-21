<?php
/* AGENZA diagnostics — visit https://yourdomain.com/api/test.php
   Shows server checks WITHOUT exposing the API key.
   Delete this file after debugging if you prefer. */
header('Content-Type: application/json; charset=utf-8');

$out = [
  'php'      => PHP_VERSION,
  'curl'     => function_exists('curl_init'),
  'mbstring' => function_exists('mb_substr'),
  'tmp_writable' => is_writable(sys_get_temp_dir()),
  'logs_writable' => is_writable(__DIR__),
  'method'   => $_SERVER['REQUEST_METHOD'],
];

// key check: only test validity, never print the key
$agent = __DIR__ . '/agent.php';
$key = null;
if (is_file($agent)) {
  $src = file_get_contents($agent);
  if (preg_match("/OPENAI_KEY\\s*=\\s*'([^']+)'/", $src, $m)) $key = $m[1];
}
$out['key_found'] = $key ? (substr($key, 0, 7) . '...' . substr($key, -4)) : false;

if ($key) {
  $ch = curl_init('https://api.openai.com/v1/models');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  $out['openai_http'] = $code;
  $out['openai_error'] = $err ?: null;
  $out['openai_key_valid'] = ($code === 200);
  if ($code === 200) {
    $j = json_decode($resp, true);
    $ids = array_column($j['data'] ?? [], 'id');
    $out['has_gpt4o_mini'] = in_array('gpt-4o-mini', $ids, true);
  } else {
    $out['openai_says'] = mb_substr((string)$resp, 0, 300);
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
