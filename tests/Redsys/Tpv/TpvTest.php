<?php
namespace Redsys\Tpv;

use PHPUnit_Framework_TestCase;
use Exception;

/**
 * Test Tpv
 */
class TpvTest extends PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $config = require (realpath(__DIR__.'/../../..').'/config.php');

        $tpv = new Tpv($config);
        $this->assertInstanceOf('Redsys\Tpv\Tpv', $tpv);

        return $tpv;
    }

    /**
     * @depends testInstance
     */
    public function testAmounts($tpv)
    {
        $this->assertEquals('000', $tpv->getAmount(0));
        $this->assertEquals('000', $tpv->getAmount(null));
        $this->assertEquals('400', $tpv->getAmount(4));
        $this->assertEquals('410', $tpv->getAmount(4.1));
        $this->assertEquals('410', $tpv->getAmount(4.10));
        $this->assertEquals('410', $tpv->getAmount(4.100));
        $this->assertEquals('410', $tpv->getAmount('4,10'));
        $this->assertEquals('410', $tpv->getAmount('4.10'));
        $this->assertEquals('410', $tpv->getAmount('4.1'));
        $this->assertEquals('410', $tpv->getAmount('4,1'));
        $this->assertEquals('040', $tpv->getAmount(0.4));
        $this->assertEquals('004', $tpv->getAmount(0.04));
        $this->assertEquals('000', $tpv->getAmount(0.004));
        $this->assertEquals('001', $tpv->getAmount(0.006));
        $this->assertEquals('400', $tpv->getAmount('4€'));
        $this->assertEquals('100050', $tpv->getAmount('1.000,50'));
    }

    /**
     * @depends testInstance
     */
    public function testFormFields($tpv)
    {
        try {
            $tpv->setFormHiddens([
                'TransactionType' => '0',
            ]);
        } catch (Exception $e) {
            $this->assertContains('Amount', $e->getMessage());
        }

        try {
            $tpv->setFormHiddens([
                'TransactionType' => '0',
                'Amount' => '1,1',
            ]);
        } catch (Exception $e) {
            $this->assertContains('Order', $e->getMessage());
        }

        try {
            $tpv->setFormHiddens([
                'TransactionType' => '0',
                'Amount' => '1,1',
                'Order' => 'abcd1234',
            ]);
        } catch (Exception $e) {
            $this->assertContains('First four order digits', $e->getMessage());
        }

        try {
            $tpv->setFormHiddens([
                'TransactionType' => '0',
                'Amount' => '1,1',
                'Order' => '1234abcd',
            ]);
        } catch (Exception $e) {
            $this->assertContains('MerchantURL', $e->getMessage());
        }

        $tpv->setFormHiddens([
            'TransactionType' => '0',
            'Amount' => '1,1',
            'Order' => '1234abcd',
            'MerchantURL' => 'http://example.com',
        ]);

        $fields = $tpv->getFormHiddens();

        $this->assertContains('<input', $fields);
        $this->assertContains('Ds_SignatureVersion', $fields);
        $this->assertContains('Ds_MerchantParameters', $fields);
        $this->assertContains('Ds_Signature', $fields);

        return $tpv;
    }
}
