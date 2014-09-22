<?php

date_default_timezone_set('Asia/Samarkand');

Yii::import('application.modules.wallet.components.*');
Yii::import('application.modules.wallet.components.payment.PayPal');
Yii::import('application.modules.wallet.models.*');

/**
 * PayPal gateway test
 *
 * @author chriss
 */
class PayPalTest extends CDbTestCase {

    public $fixtures = array(
        'wallet_gateway_settings' => 'WGatewaySettings',
        'wallet_deposit_request' => 'WDepositRequest',
        'wallet_withdraw_request' => 'WWithdrawRequest',
    );

    public function test__construct() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);
        $this->assertEquals(
                array('deposit' => 'deposit', 'withdraw' => 'withdrawByRequest'), $gateway::$actions);
    }

    public function testGetBalance() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);
        $this->assertTrue(is_numeric($gateway->getBalance()));
    }

    public function testPrepareSubmit() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);
        $gateway->prepareSubmit();
    }

    public function testGetTransactionType() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);

        $gateway->setIPN(array());
        $this->assertFalse($gateway->getTransactionType());

        $gateway->setIPN($this->getDepositIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'deposit');
        $this->assertEquals('deposit', $gateway->getTransactionType());

        $gateway->setIPN($this->getWithdrawIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'withdraw');

        $this->assertEquals('withdrawByRequest', $gateway->getTransactionType());
    }

    public function testGetTransactionAmount() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);
        $paypalMock = $this->getMock('PayPal', array('getTransactionDetails'));

        $paypalMock->setIPN(array());
        $paypalMock->getTransactionType();
        $this->assertFalse($paypalMock->getTransactionAmount());

        $paypalMock->setIPN($this->getDepositIpn());
        $this->assertFalse($paypalMock->getTransactionAmount());

        $paypalMock->setIPN($this->getDepositIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'deposit');
        $paypalMock->getTransactionType();
        $this->assertEquals('deposit', $paypalMock->transactionType);
        $paypalMock->transactionId = 1;
        $paypalMock->expects($this->once())
                ->method('getTransactionDetails')->with('1')
                ->will($this->returnValue(array('AMT' => '3.09', 'FEEAMT' => '0.09')));
        $this->assertEquals('3.00', $paypalMock->getTransactionAmount());

        $gateway->setIPN($this->getWithdrawIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'withdraw');
        $gateway->getTransactionType();
        $this->transactionId = 1;
        $this->assertEquals('1.00', $gateway->getTransactionAmount());
    }

    public function testGetTransactionObject() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);

        $gateway->setIPN(array());
        $gateway->getTransactionType();
        $this->assertFalse($gateway->getTransactionObject());

        $gateway->setIPN($this->getDepositIpn());
        $this->assertFalse($gateway->getTransactionObject());

        $gateway->setIPN($this->getDepositIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'deposit', 'rid' => '123');
        $gateway->getTransactionType();
        $o = $gateway->getTransactionObject();
        $this->assertEquals('1', $o);

        $gateway->setIPN($this->getWithdrawIpn());
        $gateway->getTransactionType();
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'withdraw', 'rid' => '123');
        $this->assertEquals('1', $gateway->getTransactionObject());
    }

    public function testValidateNotification() {
        $this->markTestSkipped('Could not test php input');
    }

    public function testGetGatewayOwner() {
        $gateway = WPaymentGateway::factory($this->wallet_gateway_settings['sample1']['id']);

        $gateway->setIPN(array());
        $this->assertFalse($gateway->getGatewayOwner());

        $gateway->setIPN($this->getDepositIpn());
        $gateway->setPlainIpn($this->getPlainDepositIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'deposit');
        $gateway->getTransactionType();
        $this->assertEquals('chriss_1335786432_biz@gmail.com', $gateway->getGatewayOwner());

        $gateway->setIPN($this->getWithdrawIpn());
        $gateway->setPlainIpn($this->getPlainWithdrawIpn());
        $_GET = array('type' => 'PayPal', 'account_id' => '1', 'action' => 'withdraw');
        $gateway->getTransactionType();
        $this->assertEquals('chriss_1335786432_biz@gmail.com', $gateway->getGatewayOwner());
    }

    public function testRefund() {
        $mock = $this->getMock('PayPal', array('_callAdaptive'));
        $this->assertInstanceOf('PayPal', $mock);
        $mock->expects($this->once())->method('_callAdaptive')->will($this->returnValue($this->getWithdrawResponse()));
        $mock->gatewayUrl = $this->wallet_gateway_settings['sample1']['url'];
        $mock->refund(0, 0, 0);
    }

    /**
     * Simulate PayPal IPN response upon money deposit
     * @return array
     */
    private function getDepositIpn() {
        return unserialize('a:18:{s:20:"payment_request_date";s:28:"Wed Aug 08 10:45:30 PDT 2012";s:10:"return_url";s:26:"http://terminals.ripley.tk";s:10:"fees_payer";s:12:"EACHRECEIVER";s:20:"ipn_notification_url";s:139:"http://terminals.ripley.tk/index.php?r=wallet/default/callback&type=PayPal&account_id=1&action=deposit&rid=489dfc88433e1b7f51267e1680409b0a";s:12:"sender_email";s:31:"chriss_1335945465_per@gmail.com";s:11:"verify_sign";s:56:"AY-.eU2nVJtr6hCs8Qyrt63wPy48APatsJEy.Vqy4J..veF3p3tOUXGs";s:8:"test_ipn";s:1:"1";s:11:"transaction";a:1:{i:0;s:8:"USD 5.00";}s:10:"cancel_url";s:26:"http://terminals.ripley.tk";s:7:"pay_key";s:20:"AP-98685127FU114315F";s:11:"action_type";s:3:"PAY";s:4:"memo";s:16:"Texgames deposit";s:16:"transaction_type";s:20:"Adaptive Payment PAY";s:6:"status";s:9:"COMPLETED";s:43:"log_default_shipping_address_in_transaction";s:5:"false";s:7:"charset";s:12:"windows-1252";s:14:"notify_version";s:11:"UNVERSIONED";s:38:"reverse_all_parallel_payments_on_error";s:5:"false";}');
    }

    /**
     * Simulate PayPal IPN response upon money deposit
     * @return array
     */
    private function getPlainDepositIpn() {
        return 'payment_request_date=Wed Aug 08 10:45:30 PDT 2012&cancel_url=http://terminals.ripley.tk&transaction[0].paymentType=DIGITALGOODS&pay_key=AP-98685127FU114315F&transaction[0].status=Completed&sender_email=chriss_1335945465_per@gmail.com&charset=windows-1252&transaction[0].pending_reason=NONE&log_default_shipping_address_in_transaction=false&verify_sign=An5ns1Kso7MWUdW4ErQKJJJ4qi4-AYieK7NeALscRXrQczqb7AZM4kDM&test_ipn=1&status=COMPLETED&ipn_notification_url=http://terminals.ripley.tk/index.php?r=wallet/default/callback&type=PayPal&account_id=1&action=deposit&rid=489dfc88433e1b7f51267e1680409b0a&' . urlencode('transaction[0]') . '.receiver=chriss_1335786432_biz@gmail.com&fees_payer=EACHRECEIVER&transaction[0].id_for_sender_txn=8NV92298BS008173C&transaction[0].status_for_sender_txn=Completed&return_url=http://terminals.ripley.tk&transaction[0].is_primary_receiver=false&transaction[0].id=13M57611318725045&transaction_type=Adaptive Payment PAY&reverse_all_parallel_payments_on_error=false&resend=true&action_type=PAY&notify_version=UNVERSIONED&transaction[0].amount=USD 5.00&memo=Texgames deposit';
    }

    /**
     * Simulate PayPal IPN response upon money withdraw
     * @return array
     */
    private function getWithdrawIpn() {
        return unserialize('a:18:{s:20:"payment_request_date";s:28:"Fri Jun 01 01:11:32 PDT 2012";s:10:"return_url";s:17:"http://penson.my/";s:10:"fees_payer";s:12:"EACHRECEIVER";s:20:"ipn_notification_url";s:109:"http://terminals.ripley.tk/index.php?r=wallet/default/callback&type=PayPal&account_id=1&action=withdraw&rid=1";s:12:"sender_email";s:31:"chriss_1335786432_biz@gmail.com";s:11:"verify_sign";s:56:"AiPC9BjkCyDFQXbSkoZcgqH3hpacAB0Uh9JCTc6kn24dJ.GI45mgdCcp";s:8:"test_ipn";s:1:"1";s:11:"transaction";a:1:{i:0;s:8:"USD 1.00";}s:10:"cancel_url";s:17:"http://penson.my/";s:7:"pay_key";s:20:"AP-3S6820598U7122709";s:11:"action_type";s:3:"PAY";s:4:"memo";s:29:"Adaptive payments Penson Test";s:16:"transaction_type";s:20:"Adaptive Payment PAY";s:6:"status";s:9:"COMPLETED";s:43:"log_default_shipping_address_in_transaction";s:5:"false";s:7:"charset";s:12:"windows-1252";s:14:"notify_version";s:11:"UNVERSIONED";s:38:"reverse_all_parallel_payments_on_error";s:5:"false";}');
    }

    /**
     * Simulate PayPal IPN response upon money withdraw
     * @return array
     */
    private function getPlainWithdrawIpn() {
        return urlencode('payment_request_date=Mon Aug 13 04:45:07 PDT 2012&return_url=http://billing.ripley.tk&fees_payer=EACHRECEIVER&ipn_notification_url=http://billing.ripley.tk/index.php?r=wallet/default/callback&type=PayPal&account_id=1&action=withdraw&rid=854dae62fe49593c21f74d7d245a3248&sender_email=chriss_1335786432_biz@gmail.com&verify_sign=AP8.jN-4MobVoTaU-EE7UQJSjWorAcLf5n16CjbjtMhjHl5RjLuE.sRO&test_ipn=1&transaction[0].id_for_sender_txn=37S91888WD246603K&' . urlencode('transaction[0]') . '.receiver=buyer_1342028125@ano.biz&cancel_url=http://billing.ripley.tk&transaction[0].is_primary_receiver=false&pay_key=AP-99H97666WV892631P&action_type=PAY&memo=TexGames refund by request #39&transaction[0].status_for_sender_txn=Pending&transaction[0].pending_reason=UNILATERAL&transaction_type=Adaptive Payment PAY&transaction[0].amount=USD 4564.00&status=COMPLETED&log_default_shipping_address_in_transaction=false&charset=windows-1252&notify_version=UNVERSIONED&reverse_all_parallel_payments_on_error=false');
    }

    /**
     * Simulate PayPal refund API response
     * @return type 
     */
    private function getWithdrawResponse() {
        return array(
            'responseEnvelope.timestamp' => '2012-07-09 07:14:13',
            'responseEnvelope.ack' => 'Success',
            'responseEnvelope.correlationId' => 'f690bbf81125e',
            'responseEnvelope.build' => 'DEV',
            'payKey' => 'AP-6W224521B0561774D',
            'paymentExecStatus' => 'COMPLETED'
        );
    }

}

?>
