<?php declare(strict_types=1);
$server_private = rtrim(file_get_contents('../keys/private.key'));
$server_public = rtrim(file_get_contents('../keys/public.key'));

function base64encode(string $string): string
{
    return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}

function base64decode(string $string): string
{
    return base64_decode(strtr($string, '-_', '+/'));
}

function x962encode(OpenSSLAsymmetricKey $key): string
{
    $ec = openssl_pkey_get_details($key)['ec'];
    return $ec['d'] ?? "\04" . $ec['x'] . $ec['y'];
}

function x962decode(string $string): OpenSSLAsymmetricKey
{
    $ec = ['curve_name' => 'prime256v1'];
    if (mb_strlen($string, '8bit') == 65) {
        $ec['x'] = mb_substr($string, 1, 32, '8bit');
        $ec['y'] = mb_substr($string, 33, 32, '8bit');
    } else {
        $ec['d'] = $string;
    }
    return openssl_pkey_new(['ec' => $ec]);
}

function asn1encode(string $signature): string
{
    $data = bin2hex($signature);
    $position = 6;
    $length = hexdec(mb_substr($data, $position, 2, '8bit')) * 2;
    $position += 2;
    $r = asn1retrieve(mb_substr($data, $position, $length, '8bit'));
    $position += $length + 2;
    $length = hexdec(mb_substr($data, $position, 2, '8bit')) * 2;
    $position += 2;
    $s = asn1retrieve(mb_substr($data, $position, $length, '8bit'));
    return hex2bin(str_pad($r, 64, '0', STR_PAD_LEFT) . str_pad($s, 64, '0', STR_PAD_LEFT));
}

function asn1retrieve(string $data): string
{
    while (mb_strpos($data, '00', 0, '8bit') === 0 && mb_substr($data, 2, 2, '8bit') > '7f') {
        $data = mb_substr($data, 2, null, '8bit');
    }
    return $data;
}

function send(string $payload, string $input, string $server_private, string $server_public): int
{
    /** @var object $subscription */
    $subscription = json_decode($input, false);
    /** @var string $endpoint */
    $endpoint = $subscription->endpoint;
    /** @var string $encoding */
    $encoding = $subscription->contentEncoding;
    /** @var string $client_public */
    $client_public = base64decode($subscription->keys->p256dh);
    /** @var string $client_secret */
    $client_secret = base64decode($subscription->keys->auth);
    $salt = random_bytes(16);
    $random_private = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $random_public = x962encode(openssl_pkey_get_public(openssl_pkey_get_details($random_private)['key']));
    $ikm = openssl_pkey_derive(x962decode($client_public), $random_private, 256);
    $prk = hash_hkdf('sha256', $ikm, 32, 'WebPush: info' . chr(0) . $client_public . $random_public, $client_secret);
    $cek = hash_hkdf('sha256', $prk, 16, 'Content-Encoding: aes128gcm' . chr(0), $salt);
    $nonce = hash_hkdf('sha256', $prk, 12, 'Content-Encoding: nonce' . chr(0), $salt);
    $header = $salt . pack('N*', 4096) . pack('C*', mb_strlen($random_public, '8bit')) . $random_public;
    $tag = '';
    $cipher = openssl_encrypt(str_pad($payload . chr(2), 3052, chr(0), STR_PAD_RIGHT), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag) . $tag;
    $post = $header . $cipher;
    $claims = base64encode(json_encode([
        'typ' => 'JWT',
        'alg' => 'ES256',
    ])) . '.' . base64encode(json_encode([
        'aud' => parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
        'exp' => strtotime('+12 hour'),
        'sub' => 'mailto:17925623+naoigcat@users.noreply.github.com',
    ]));
    $signature = '';
    openssl_sign($claims, $signature, x962decode(base64decode($server_private)), 'sha256');
    $jwt = $claims . '.' . base64encode(asn1encode($signature));
    $curl = curl_init($endpoint);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'TTL: 2419200',
        'Content-Type: application/octet-stream',
        'Content-Length: ' . mb_strlen($post, '8bit'),
        'Content-Encoding: ' . $encoding,
        'Authorization: vapid t=' . $jwt . ', k=' . $server_public,
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $code;
}

if ($input = file_get_contents('php://input')) {
    $payload = json_encode([
        'title' => 'Notification',
        'body' => 'Notification is pushed.',
        'timestamp' => time(),
        'tag' => 'tag',
    ]);
    send($payload, $input, $server_private, $server_public);
    exit;
}
?>
<html>
<head>
    <title>Web Push Demo</title>
    <script>
		const base64ToUInt8Array = string => {
			const raw = atob((string + "=".repeat((4 - (string.length % 4)) % 4)).replace(/\-/g, "+").replace(/_/g, "/"));
			const output = new Uint8Array(raw.length);
			for (let i = 0; i < raw.length; ++i) {
				output[i] = raw.charCodeAt(i);
			}
			return output;
		};
		function subscribe() {
			navigator.serviceWorker.register('service_worker.js');
			navigator.serviceWorker.ready.then(function(registration) {
				return registration.pushManager.getSubscription().then(function(subscription) {
					if (subscription) return subscription;
					return registration.pushManager.subscribe({
						userVisibleOnly: true,
						applicationServerKey: base64ToUInt8Array('<?php echo $server_public ?>'),
					});
				});
			}).then(function (subscription) {
				let json = subscription.toJSON();
				json.contentEncoding = 'aes128gcm';
				fetch(new Request('', {method: 'POST', body: JSON.stringify(json)}));
			});
		}
    </script>
</head>
<body>
    <h1>Web Push Demo</h1>
	<button id='subscribe' onclick='subscribe();'>Subscribe and Push</button>
</body>
</html>
