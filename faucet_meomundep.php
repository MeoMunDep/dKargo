<?php


define(
    'BANNER',
    <<<BANNER
   o o  o                  
   | | /                   
 o-O OO    oo o-o o--o o-o 
|  | | \\  | | |   |  | | | 
 o-o o  o o-o-o   o--O o-o 
                     |     
                  o--o     

BANNER
);


function clearConsole(): void
{
    for ($i = 0; $i < 10000; $i++) {
        echo "\n";
    }
    echo "\033[2J\033[H";

    if (strncasecmp(string1: PHP_OS, string2: 'WIN', length: 3) === 0) {
        system(command: 'cls');
    } else {
        system(command: 'clear');
    }
}


function color($text, $colorCode): string
{
    return "\033[" . $colorCode . "m" . $text . "\033[0m";
}

function delay($ms): void
{
    usleep(microseconds: $ms * 1000);
}

function countdown(int $seconds): void
{
    for ($i = $seconds; $i >= 0; $i--) {
        echo "\r" . color(text: "⏳ Countdown: $i seconds ", colorCode: "33");
        flush();
        delay(ms: 1000);
    }
    echo "\r" . color(text: "✅ Countdown complete!          \n", colorCode: "32");
}

function loadWalletsWithProxies(): array|null
{
    if (!file_exists(filename: 'addresses.txt')) {
        echo color(text: "[ERROR] addresses.txt not found!\n", colorCode: '1;31');
        return null;
    }

    $addresses = file(filename: 'addresses.txt', flags: FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $proxies = file_exists(filename: 'proxies.txt')
        ? file(filename: 'proxies.txt', flags: FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        : [];

    if (empty($addresses)) {
        echo color(text: "[ERROR] addresses.txt is empty or invalid.\n", colorCode: '1;31');
        return null;
    }

    $result = [];
    foreach ($addresses as $i => $addr) {
        $addr = trim(string: $addr);
        if (preg_match(pattern: "/^0x[a-fA-F0-9]{40}$/", subject: $addr)) {
            $proxy = $proxies[$i] ?? ($proxies ? $proxies[array_rand(array: $proxies)] : null);
            $result[] = ['wallet' => $addr, 'proxy' => $proxy];
        }
    }

    if (empty($result)) {
        echo color(text: "[ERROR] No valid wallet address found in addresses.txt\n", colorCode: '1;31');
        return null;
    }

    return $result;
}

function parseProxy($proxyStr): array
{
    $proxy = ['type' => null, 'auth' => false, 'url' => null];
    $proxyStr = trim(string: $proxyStr);

    if (str_starts_with(haystack: $proxyStr, needle: 'socks5://')) {
        $proxy['type'] = CURLPROXY_SOCKS5;
        $proxyStr = substr(string: $proxyStr, offset: 9);
    } elseif (str_starts_with(haystack: $proxyStr, needle: 'http://')) {
        $proxy['type'] = CURLPROXY_HTTP;
        $proxyStr = substr(string: $proxyStr, offset: 7);
    } else {
        $proxy['type'] = CURLPROXY_HTTP;
    }

    if (str_contains(haystack: $proxyStr, needle: '@')) {
        [$auth, $host] = explode(separator: '@', string: $proxyStr, limit: 2);
        $proxy['auth'] = $auth;
        $proxy['url'] = $host;
    } else {
        $proxy['url'] = $proxyStr;
    }

    return $proxy;
}

function extractJsonFromResponse($response): mixed
{
    $pos = strpos(haystack: $response, needle: '1:');
    if ($pos === false) {
        return false;
    }

    $jsonPart = substr(string: $response, offset: $pos + 2);
    $jsonPart = trim(string: $jsonPart);

    $json = json_decode(json: $jsonPart, associative: true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo color(text: "[ERROR] JSON decode failed: " . json_last_error_msg() . "\n", colorCode: '1;31');
        return false;
    }

    return $json;
}

function debugResponse($wallet, $response, $httpCode = null, $curlError = null)
{
    $logPrefix = "[DEBUG][$wallet]";

    if ($httpCode !== null) {
        echo color(text: "$logPrefix HTTP Status: $httpCode\n", colorCode: '0;37');
    }

    if ($curlError) {
        echo color(text: "$logPrefix Curl Error: $curlError\n", colorCode: '1;31');
    }

    echo "----------------------------------------\n";
    echo $response . "\n";
    echo "----------------------------------------\n";
    echo color(text: "$logPrefix Raw Response End\n", colorCode: '0;37');
}

function requestFaucet($wallet, $proxyInfo = null): bool|string
{
    $nextActions = [
        "ec79a589caa1d8f6f29be2f7277e3bab9b172c4c",
        "43e882fa7398c9d28975b0ceac191a7190ca19a6",
        "215afdc0080113069fe421716d9041974f3e5ed6",
    ];

    foreach ($nextActions as $step => $nextAction) {
        echo color(text: "[INFO]", colorCode: '1;34') . " Step " . ($step + 1) . ": Using next-action \033[31m$nextAction\033[0m\n";

        $headers = [
            'authority: dkargo.io',
            'method: POST',
            'path: /en/developers/faucet',
            'scheme: https',
            'accept: text/x-component',
            'accept-language: vi-VN,vi;q=0.9,en-GB;q=0.8,en;q=0.7',
            'cache-control: no-cache',
            'content-type: text/plain;charset=UTF-8',
            "next-action: $nextAction",
            'origin: https://dkargo.io',
            'pragma: no-cache',
            'cookie: NEXT_LOCALE=en',
            'referer: https://dkargo.io/en/developers/faucet',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36'
        ];

        $data = json_encode(value: [$wallet]);

        $ch = curl_init();
        curl_setopt(handle: $ch, option: CURLOPT_SSL_VERIFYPEER, value: false);
        curl_setopt(handle: $ch, option: CURLOPT_SSL_VERIFYHOST, value: false);
        curl_setopt(handle: $ch, option: CURLOPT_URL, value: 'https://dkargo.io/en/developers/faucet');
        curl_setopt(handle: $ch, option: CURLOPT_POST, value: true);
        curl_setopt(handle: $ch, option: CURLOPT_POSTFIELDS, value: $data);
        curl_setopt(handle: $ch, option: CURLOPT_HTTPHEADER, value: $headers);
        curl_setopt(handle: $ch, option: CURLOPT_RETURNTRANSFER, value: true);
        curl_setopt(handle: $ch, option: CURLOPT_TIMEOUT, value: 15);

        if ($proxyInfo) {
            $proxy = parseProxy(proxyStr: $proxyInfo);
            curl_setopt(handle: $ch, option: CURLOPT_PROXY, value: $proxy['url']);
            curl_setopt(handle: $ch, option: CURLOPT_PROXYTYPE, value: $proxy['type']);
            if ($proxy['auth']) {
                curl_setopt(handle: $ch, option: CURLOPT_PROXYUSERPWD, value: $proxy['auth']);
            }
        }

        $response = curl_exec(handle: $ch);
        $curlError = curl_error(handle: $ch);
        $httpCode = curl_getinfo(handle: $ch, option: CURLINFO_HTTP_CODE);
        curl_close(handle: $ch);

        if ($response === false) {
            echo color(text: "[ERROR] Curl failed at step " . ($step + 1) . ": $curlError\n", colorCode: '1;31');
            return false;
        }

        $json = extractJsonFromResponse(response: $response);
        if (!$json) {
            echo color(text: "[ERROR] Failed to parse JSON at step " . ($step + 1) . "\n", colorCode: '1;31');
            return 'continue';
        }

        $status = $json['data']['status'] ?? $json['status'] ?? null;
        $body = $json['data']['body'] ?? $json['body'] ?? null;

        if ($status === null) {
            echo color(text: "[ERROR] Cannot find status in response at step " . ($step + 1) . "\n", colorCode: '1;31');
            return 'continue';
        }

        if ($step === 2) {
            if ($httpCode !== 200) {
                if ($httpCode == 402 || $httpCode == 407) {
                    echo color(text: "[STOP]", colorCode: '1;31') . " $wallet → HTTP Status $httpCode (Proxy auth failed!)\n";
                    return 'stop';
                } elseif ($httpCode >= 400 && $httpCode < 500) {
                    echo $response;
                    echo color(text: "[STOP]", colorCode: '1;31') . " $wallet → HTTP Status $httpCode (Client error)\n";
                    return 'stop';
                } else {
                    echo color(text: "[RETRY]", colorCode: '1;33') . " $wallet → HTTP Status $httpCode (will retry)\n";
                    return 'continue';
                }
            }

            if ($status === 200) {
                if (is_array(value: $body)) {
                    if (isset($body[0]['tx_hash'])) {
                        $txHash = $body[0]['tx_hash'];
                        $scanUrl = "https://sepolia.arbiscan.io/tx/$txHash";
                        echo color(text: "[SUCCESS]", colorCode: '1;32') . " Wallet: \033[34m$wallet\033[0m\n";
                        echo color(text: "         🔗 $scanUrl\n", colorCode: '0;36');
                        return 'success';
                    }
                }
            }
        }

        if ($step < 2) {
            continue;
        }

        if ($status === 400) {
            echo $response;
            if (isset($body['code']) && $body['code'] === 'F003') {
                echo color(text: "[FAILED]", colorCode: '1;31') . " $wallet → Faucet Already In Progress (F003)\n";
                return 'stop';
            }
            if (isset($body['message']) && strpos(haystack: $body['message'], needle: 'Faucet Already In Progress') !== false) {
                echo color(text: "[FAILED]", colorCode: '1;31') . " $wallet → Faucet Already In Progress\n";
                return 'stop';
            }
        }

        echo color(text: "[RETRY]", colorCode: '1;33') . " $wallet → API Status $status (will retry)\n";
        return 'continue';
    }

    echo color(text: "[DONE]", colorCode: '1;32') . " $wallet → Passed all next-actions\n";
    return 'success';
}

function main(): never
{
    while (true) {
        clearConsole();
        echo color(text: BANNER, colorCode: '1;36') . '';
        echo color(text: 'Faucet script by @Meomundep from @BlackCatSyndicate', colorCode: '0;33');
        echo color(text: "\n>>> 💚 Thanks for using the script!\n", colorCode: '1;32');
        echo color(text: ">>> ⭐ Don't forget to give a star to the repo: ", colorCode: '1;36') . color(text: 'https://github.com/MeoMunDep/MeoMunDep', colorCode: '0;36') . "\n";
        echo color(text: '---------------------------------------------------------------------------------------------------------', colorCode: '1;35');

        $wallets = loadWalletsWithProxies();
        if (!$wallets) exit;
        foreach ($wallets as $item) {
            $proxyLabel = $item['proxy'] ? $item['proxy'] : "no proxy";
            echo color(text: "\n[INFO] Processing wallet: {$item['wallet']} \n\tUsing PROXY: {$proxyLabel}\n", colorCode: '1;34');

            $attemptCount = 0;
            while (true) {
                $attemptCount++;
                echo color(text: "[ATTEMPT $attemptCount] Requesting faucet...\n", colorCode: '1;36');

                $result = requestFaucet(wallet: $item['wallet'], proxyInfo: $item['proxy']);

                if ($result === 'stop') {
                    break;
                } elseif ($result === 'retry' || $result === 'continue') {
                    continue;
                } elseif ($result === 'success') {
                    break;
                } else {
                    echo color(text: "[INFO] ⚠️ Unknown result, retrying...\n", colorCode: '1;33');
                    break;
                }
            }
            echo color(text: '---------------------------------------------------------------------------------------------------------', colorCode: '1;35');
            delay(ms: 500);
        }

        echo color(text: "\n>>> 💚 Thanks for using the script! Waiting 24 hours to next faucet!\n", colorCode: '1;32');
        echo color(text: ">>> ⭐ Don't forget to give star to the repo: ", colorCode: '1;36') . color(text: "\033[37mhttps://github.com/MeoMunDep/MeoMunDep\033[0m", colorCode: '0;36') . "\n";
        countdown(seconds: 86400);
    }
}
main();
