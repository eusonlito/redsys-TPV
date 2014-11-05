<?php
$step = empty($_GET['step']) ? 1 : (int)$_GET['step'];

include (__DIR__.'/src/autoload.php');

if ($step === 1) {
    # Ejemplo de pago instantáneo
    # Este proceso se realiza para pagos en el momento, sin necesidad de confirmación futura (TransactionType = 0)

    # Cargamos la clase con los parámetros base

    $TPV = new Redsys\Tpv\Tpv(array(
        'Environment' => 'test', // Puedes indicar test o real
        'MerchantCode' => '1234567890',
        'Key' => 'asdfghjkd0123456789',
        'Terminal' => '1',
        'Currency' => '978',
        'MerchantName' => 'COMERCIO',
        'Titular' => 'Mi Comercio',
        'Currency' => '978',
        'Terminal' => '1',
        'ConsumerLanguage' => '001'
    ));

    # Indicamos los campos para el pedido

    $TPV->setFormHiddens(array(
        'TransactionType' => '0',
        'MerchantData' => 'Televisor de 50 pulgadas',
        'Order' => '012121323',
        'Amount' => '568,25',
        'UrlOK' => 'http://dominio.com/direccion-todo-correcto/',
        'UrlKO' => 'http://dominio.com/direccion-error',
        'MerchantURL' => 'http://dominio.com/direccion-control-pago'
    ));

    # Imprimimos el pedido el formulario y redirigimos a la TPV

    echo '<form action="'.$TPV->getPath('/realizarPago').'" method="post">'.$TPV->getFormHiddens().'</form>';

    die('<script>document.forms[0].submit();</script>');
}

if ($step === 2) {
    # Control de respuesta del paso 1

    # Cargamos la clase con los parámetros base

    $TPV = new Redsys\Tpv\Tpv(array(
        'Environment' => 'test', // Puedes indicar test o real
        'MerchantCode' => '1234567890',
        'Key' => 'asdfghjkd0123456789',
        'Terminal' => '1',
        'Currency' => '978',
        'MerchantName' => 'COMERCIO',
        'Titular' => 'Mi Comercio',
        'Currency' => '978',
        'Terminal' => '1',
        'ConsumerLanguage' => '001'
    ));

    # Realizamos la comprobación de la transacción

    try {
        $TPV->checkTransaction($_POST);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/logs/errores-tpv.log', $e->getMessage(), FILE_APPEND);
        die();
    }

    # Actualización del registro en caso de pago (ejemplo usando mi framework)

    $Db->update(array(
        'table' => 'tpv',
        'limit' => 1,
        'data' => array(
            'operacion' => $_POST['Ds_TransactionType'],
            'fecha_pago' => date('Y-m-d H:i:s')
        ),
        'conditions' => array(
            'id' => $_POST['Ds_Order']
        )
    ));

    die();
}

if ($step === 3) {
    # Ejemplo de pago en diferido

    # Cargamos la clase con los parámetros base

    $TPV = new Redsys\Tpv\Tpv(array(
        'Environment' => 'test', // Puedes indicar test o real
        'MerchantCode' => '1234567890',
        'Key' => 'asdfghjkd0123456789',
        'Terminal' => '1',
        'Currency' => '978',
        'MerchantName' => 'COMERCIO',
        'Titular' => 'Mi Comercio',
        'Currency' => '978',
        'Terminal' => '1',
        'ConsumerLanguage' => '001'
    ));

    # Indicamos los campos para la confirmación del pago

    $TPV->sendXml(array(
        'TransactionType' => '2', // Código para la Confirmación del cargo
        'MerchantURL' => 'http://dominio.com/direccion-control-pago-xml', // A esta URL enviará el banco la confirmación del cobro
        'Amount' => '568,25', // La cantidad final a cobrar
        'Order' => '012121323', // El número de pedido, que debe existir en el sistema bancario a través de una autorización previa
        'MerchantData' => 'Televisor de 50 pulgadas',
    ));

    die();
}

if ($step === 4) {
    # Ejemplo de respuesta para el paso 3

    # Cargamos la clase con los parámetros base

    $TPV = new Redsys\Tpv\Tpv(array(
        'Environment' => 'test', // Puedes indicar test o real
        'MerchantCode' => '1234567890',
        'Key' => 'asdfghjkd0123456789',
        'Terminal' => '1',
        'Currency' => '978',
        'MerchantName' => 'COMERCIO',
        'Titular' => 'Mi Comercio',
        'Currency' => '978',
        'Terminal' => '1'
    ));

    # Obtenemos los datos remitidos por el banco en formato `array`

    $datos = $TPV->xmlString2array($_POST['datos']);

    # Realizamos la comprobación de la transacción

    try {
        $TPV->checkTransaction($datos);
    } catch (\Exception $e) {
        file_put_contents(__DIR__.'/logs/errores-tpv.log', $e->getMessage(), FILE_APPEND);
        die();
    }

    # Actualización del registro en caso de pago (ejemplo usando mi framework)

    $Db->update(array(
        'table' => 'tpv',
        'limit' => 1,
        'data' => array(
            'pagado' => 1,
            'operacion' => $datos['Ds_TransactionType'],
            'fecha_pago' => date('Y-m-d H:i:s')
        ),
        'conditions' => array(
            'id' => $datos['Ds_Order']
        )
    ));

    die();
}
