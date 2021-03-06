<?php

use WHMCS\Billing\Payment\Transaction;
use WHMCS\Database\Capsule;
use Billplz\Connect;

$base_dir = __DIR__ . '/../../../';
$includes_dir = $base_dir . 'includes/';
require $base_dir . 'init.php';
require $includes_dir . 'gatewayfunctions.php';
require $includes_dir . 'invoicefunctions.php';
require __DIR__ . '/../billplz/connect.php';

$gatewayParams = getGatewayVariables('billplz');

try {
    $data = Connect::getXSignature($gatewayParams['x_signature_key']);
} catch (\Exception $e) {
    die($e->getMessage());
}

Capsule::beginTransaction();

$db_data = Capsule::table('mod_billplz_gateway')
    ->where('bill_slug', $data['id'])
    ->take(1)
    ->lockForUpdate()
    ->first();

$invoiceId = $db_data->invoiceid;

if ($data['paid'] && $db_data->state == 'due') {
    $invoiceId = checkCbInvoiceID($db_data->invoiceid, $gatewayParams['name']);
    $db_data_trx = Transaction::where('transid', $data['id'])->take(1)->first();

    $result = $db_data_trx->transid;

    if (empty($result)) {
        $trxMessage = "Callback. Bill ({$data['id']}) Paid";
        addInvoicePayment($invoiceId, $data['id'], NULL, NULL, 'billplz');

        // Log Transaction
        logTransaction($gatewayParams['name'], $data, $trxMessage);
    }

    Capsule::table('mod_billplz_gateway')
        ->where('bill_slug', $data['id'])
        ->update(['state' => 'paid']);
}

Capsule::commit();

echo 'BILLPLZ';